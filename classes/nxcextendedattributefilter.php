<?php
/**
 * @package nxcExtendedAttributeFilter
 * @class   nxcExtendedAttributeFilter
 * @author  Serhey Dolgushev <serhey.dolgushev@nxc.no>
 * @date    12 apr 2010
 **/

class nxcExtendedAttributeFilter {

	public function __construct() {
	}

	public function getFilters( $params ) {
		$return = array(
			'tables'   => null,
			'joins'    => null,
			'columns'  => null,
			'group_by' => null
		);

		if( isset( $params['sub_filters'] ) && is_array( $params['sub_filters'] ) ) {
			$subFilters = $params['sub_filters'];
			foreach( $subFilters as $index => $subFilterInfo ) {
				if(
					isset( $subFilterInfo['callback'] ) === false ||
					is_array( $subFilterInfo['callback'] ) === false ||
					isset( $subFilterInfo['callback']['method_name'] ) === false ||
					is_scalar( $subFilterInfo['callback']['method_name'] ) === false
				) {
					continue;
				}

				$callback   = array();
				$callback[] = isset( $subFilterInfo['callback']['class_name'] ) ? $subFilterInfo['callback']['class_name'] : 'nxcExtendedAttributeFilter';
				$callback[] = $subFilterInfo['callback']['method_name'];

				$params = isset( $subFilterInfo['params'] ) ? $subFilterInfo['params'] : array();
				if( is_array( $params ) === false ) {
					$params = array( $params );
				}
				$params = array_merge( array( 'index' => $index ), $params );

				if( is_callable( $callback ) ) {
					$subFilterResult = call_user_func( $callback, $params );

					foreach( $return as $key => $value ) {
						if( isset( $subFilterResult[ $key ] ) && is_scalar( $subFilterResult[ $key ] ) ) {
							$return[ $key ] = $value . $subFilterResult[ $key ];
						}
					}
				}
			}
		}

		if( is_null( $return['group_by'] ) ) {
			unset( $return['group_by'] );
		}
		return $return;
	}

	public static function userAccount( $params ) {
		$db = eZDB::instance();

		$joins = 'ezuser.contentobject_id = ezcontentobject.id AND ezuser_setting.user_id = ezcontentobject.id AND ';
		if( isset( $params['login'] ) ) {
			$joins .= 'ezuser.login LIKE "%' . $db->escapeString( $params['login'] ) . '%" AND ';
		}
		if( isset( $params['email'] ) ) {
			$joins .= 'ezuser.email LIKE "%' . $db->escapeString( $params['email'] ) . '%" AND ';
		}
		if( isset( $params['enabled'] ) ) {
			$joins .= 'ezuser_setting.is_enabled=' . (int) $params['enabled'] . ' AND ';
		}
		$return = array(
			'tables'  => ', ezuser, ezuser_setting',
			'joins'   => $joins
		);
		return $return;
	}

	public static function relatedObjectList( $params ) {
		$db = eZDB::instance();

		$table = 'ol' . $params['index'];
		$joins = ' ezcontentobject_tree.contentobject_id = ' . $table . '.from_contentobject_id AND ezcontentobject_tree.contentobject_version = ' . $table . '.from_contentobject_version AND ';

		if( isset( $params['attribute'] ) ) {
			$attributeID = $params['attribute'];
			if( is_numeric( $attributeID ) === false ) {
				$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $params['attribute'] );
			}
			$joins .= '' . $table . '.contentclassattribute_id = ' . $attributeID;
		}

		if( isset( $params['object_ids'] ) ) {
			$objectIDs     = $params['object_ids'];
			$excludeString = null;
			if( isset( $params['exclude'] ) && $params['exclude'] === true ) {
				$excludeString = is_array( $objectIDs ) ? 'NOT' : '!';
			}
			if( is_array( $objectIDs ) ) {
				foreach( $objectIDs as $key => $id ) {
					if( is_numeric( $id ) === false ) {
						unset( $objectIDs[ $key ] );
					}
				}

				if( count( $objectIDs ) > 0 ) {
					$joins .= ' AND ' . $table . '.to_contentobject_id ' . $excludeString . ' IN (' . join( ',', $objectIDs ) . ') AND ';
				} else {
					return array();
				}
			} else {
				$joins .= ' AND ' . $table . '.to_contentobject_id ' . $excludeString . '=' . (int) $objectIDs . ' AND ';
			}
		}

		$return = array(
			'tables'  => ', ezcontentobject_link as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function reverseRelatedObjectList( $params ) {
		$db = eZDB::instance();

		$table = 'rol' . $params['index'];
		$joins = ' ezcontentobject_tree.contentobject_id = ' . $table . '.to_contentobject_id AND ';

		if( isset( $params['attribute'] ) ) {
			$attributeID = $params['attribute'];
			if( is_numeric( $attributeID ) === false ) {
				$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $params['attribute'] );
			}
			$joins .= '' . $table . '.contentclassattribute_id = ' . $attributeID;
		}

		if( isset( $params['object_id'] ) ) {
			$object = eZContentObject::fetch( $params['object_id'] );
			if( $object instanceof eZContentObject ) {
				$joins .=
					' AND ' . $table . '.from_contentobject_id = ' . (int) $object->attribute( 'id' )
					. ' AND ' . $table . '.from_contentobject_version = ' . (int) $object->attribute( 'current_version' ) . ' AND ';
			}
		}

		$return = array(
			'tables'  => ', ezcontentobject_link as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function birthday( $params ) {
		$table = 'birthdate' . $params['index'];
		$joins = $table . '.contentobject_id = ezcontentobject.id AND ' . $table . '.version = ezcontentobject.current_version AND ' . $table . '.data_type_string = "ezbirthday" AND ';
		if( isset( $params['start_timestamp'] ) ) {
			$joins .= 'DATE( ' . $table . '.data_text ) >= DATE( "' . date( 'Y-m-d', $params['start_timestamp'] ) . '" ) AND ';
		}
		if( isset( $params['end_timestamp'] ) ) {
			$joins .= 'DATE( ' . $table . '.data_text ) <= DATE( "' . date( 'Y-m-d', $params['end_timestamp'] ) . '" ) AND ';
		}

		$return = array(
			'tables'  => ', ezcontentobject_attribute as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function nodeIDs( $params ) {
		$return = array(
			'joins' => 'ezcontentobject_tree.node_id IN (' . implode( ', ', $params['nodeIDs'] ). ') AND '
		);
		return $return;
	}

	public static function geoLocation( $params ) {
		$return = array();

		$latAttributeID = ( is_numeric( $params['attributes']['lat'] ) === false ) ? eZContentObjectTreeNode::classAttributeIDByIdentifier( $params['attributes']['lat'] ) : $params['attributes']['lat'];
		$lngAttributeID = ( is_numeric( $params['attributes']['lon'] ) === false ) ? eZContentObjectTreeNode::classAttributeIDByIdentifier( $params['attributes']['lon'] ) : $params['attributes']['lon'];

		if(
			$latAttributeID !== false
			&& $lngAttributeID !== false
		) {
			$tables = ', ezcontentobject_attribute as lat, ezcontentobject_attribute as lon';

			$joins = 'lat.contentobject_id = ezcontentobject.id AND lat.version = ezcontentobject.current_version AND lat.contentclassattribute_id = ' . $latAttributeID . ' AND ';
			$joins .= 'lon.contentobject_id = ezcontentobject.id AND lon.version = ezcontentobject.current_version AND lon.contentclassattribute_id = ' . $lngAttributeID . ' AND ';
			$joins .= '( ( ACOS( SIN( lat.data_float * PI() / 180 ) * SIN( ' . $params['lat'] . ' * PI() / 180 ) + COS( lat.data_float * PI() / 180 ) * COS( ' . $params['lat'] . ' * PI() / 180 ) * COS( ( lon.data_float - ' . $params['lon'] . ' ) * PI() / 180 ) ) * 180 / PI() ) * 60 * 1.1515 ) * 1.609344 < ' . $params['distance'] . ' AND ';

			$return = array(
				'tables'  => $tables,
				'joins'   => $joins
			);
		}

		return $return;
	}

	public static function datesRange( $params ) {
		$attributes = $params['attributes'];
		$range      = $params['range'];

		$startDateAttributeID = isset( $attributes['start'] ) ? $attributes['start'] : false;
		if( is_numeric( $startDateAttributeID ) === false ) {
			$startDateAttributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $startDateAttributeID );
		}
		$endDateAttributeID = isset( $attributes['end'] ) ? $attributes['end'] : false;
		if( is_numeric( $endDateAttributeID ) === false ) {
			$endDateAttributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $endDateAttributeID );
		}
		if(
			$startDateAttributeID === false
			|| $endDateAttributeID === false
		) {
			return array();
		}

		$startDateVar = 'sd' . $params['index'];
		$endDateVar   = 'ed' . $params['index'];
		$tables = ', ezcontentobject_attribute as ' . $startDateVar
			. ', ezcontentobject_attribute as ' . $endDateVar;
		$joins = $startDateVar . '.contentobject_id = ezcontentobject.id AND ' .
			$startDateVar . '.version = ezcontentobject.current_version AND ' .
			$startDateVar . '.contentclassattribute_id = ' . $startDateAttributeID . ' AND ';
		$joins .= $endDateVar . '.contentobject_id = ezcontentobject.id AND ' .
			$endDateVar . '.version = ezcontentobject.current_version AND ' .
			$endDateVar . '.contentclassattribute_id = ' . $endDateAttributeID . ' AND ';

		$startDate = isset( $range['start'] ) ? $range['start'] : false;
		if( is_numeric( $startDate ) === false ) {
			$startDate = (int) strtotime( $startDate );
		}
		$endDate = isset( $range['end'] ) ? $range['end'] : false;
		if( is_numeric( $endDate ) === false ) {
			$endDate = (int) strtotime( $endDate );
		}
		if(
			$startDate > 0
			&& $endDate > 0
		) {
			$startDateValueVar = $startDateVar . '.data_int';
			$endDateValueVar   = $endDateVar . '.data_int';
			$joins .=
				'('
				. '(' . $startDateValueVar . ' >= ' . $startDate . ' AND ' . $startDateValueVar . ' <= ' . $endDate . ') '
				. 'OR (' . $endDateValueVar . ' >= ' . $startDate . ' AND ' . $endDateValueVar . ' <= ' . $endDate . ') '
				. 'OR (' . $startDateValueVar . ' <= ' . $startDate . ' AND ' . $endDateValueVar . ' >= ' . $endDate . ')'
				. ') AND ';
			return array(
				'tables'  => $tables,
				'joins'   => $joins
			);
		}
	}

	public static function groupBy( $params ) {
		return array(
			'group_by' => 'GROUP BY ' . $params['field']
		);
	}

	public static function randomOrder( $params ) {
		return array(
			'columns' => ', RAND() as random_order'
		);
	}
}
?>