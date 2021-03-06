<?php
/*
 * Copyright (c) Patrick Hayes
 * Copyright (c) 2010-2011, Arnaud Renevier
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/KML encoder/decoder
 *
 * Mainly inspired/adapted from OpenLayers( http://www.openlayers.org ) 
 *   Openlayers/format/WKT.js
 *
 * @package    sfMapFishPlugin
 * @subpackage GeoJSON
 * @author     Camptocamp <info@camptocamp.com>
 */
class KML extends GeoAdapter
{

  /**
   * Read KML string into geometry objects
   *
   * @param string $kml A KML string
   *
   * @return Geometry|GeometryCollection
   */
  public function read($kml)
  {
    return $this->geomFromText($kml);
  }

  /**
   * Serialize geometries into a KML string.
   *
   * @param Geometry $geometry
   *
   * @return string The KML string representation of the input geometries
   */
  public function write(Geometry $geometry)
  {
    return $this->geometryToKML($geometry);
  }
  
  public function geomFromText($text) {
        // Change to lower-case and strip all CDATA
        $text = strtolower($text);
        $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s','',$text);
        
        // Load into DOMDOcument
        $xmlobj = new DOMDocument();
        $xmlobj->loadXML($text);
        if ($xmlobj === false) {
          throw new Exception("Invalid KML: ". $text);
        }
        
        $this->xmlobj = $xmlobj;
        try {
          $geom = $this->geomFromXML();
        } catch(InvalidText $e) {
            throw new Exception("Cannot Read Geometry From KML: ". $text);
        } catch(Exception $e) {
            throw $e;
        }

        return $geom;
    }
    
    protected function geomFromXML() {
    	$geometries = array();
      $geom_types = geoPHP::geometryList();
      $placemark_elements = $this->xmlobj->getElementsByTagName('placemark');
      foreach ($placemark_elements as $placemark) {
      	foreach ($placemark->childNodes as $child) {
      		// Node names are all the same, except for MultiGeometry, which maps to GeometryCollection
      		$node_name = $child->nodeName == 'multigeometry' ? 'geometrycollection' : $child->nodeName;
      		if (array_key_exists($node_name, $geom_types)) {
      			$function = 'parse'.$geom_types[$node_name];
      			$geometries[] = $this->$function($child);
      		}
      	}
      }
      return geoPHP::geometryReduce($geometries); 
    }
    
    protected function childElements($xml, $nodename = '') {
    	$children = array();
      foreach ($xml->childNodes as $child) {
        if ($child->nodeName == $nodename) {
        	$children[] = $child;
      	}
      }
      return $children;
    }
    
    protected function parsePoint($xml) {
      $coordinates = $this->_extractCoordinates($xml);
      return new Point(floatval($coordinates[0][0]),floatval($coordinates[0][1]));
    }
    
    protected function parseLineString($xml) {
      $coordinates = $this->_extractCoordinates($xml);
      $point_array = array();
      foreach ($coordinates as $set) {
      	$point_array[] = new Point(floatval($set[0]),floatval($set[1]));
      }
      return new LineString($point_array);
    }
    
    protected function parseLinearRing($xml) {
      $coordinates = $this->_extractCoordinates($xml);
      $components = array();
      foreach ($coordinates as $set) {
      	$components[] = new Point($set[0],$set[1]);
      }
      return new LinearRing($components);
    }
    
    protected function parsePolygon($xml) {
      $components = array();
      
      $outer_boundary_element_a = $this->childElements($xml, 'outerboundaryis');
      $outer_boundary_element = $outer_boundary_element_a[0];
      $outer_ring_element_a = $this->childElements($outer_boundary_element, 'linearring');
      $outer_ring_element = $outer_ring_element_a[0];
      $components[] = $this->parseLinearRing($outer_ring_element);
      
      if (count($components) != 1) {
        throw new Exception("Invalid KML");
      }
      
      $inner_boundary_element_a = $this->childElements($xml, 'innerboundaryis');
        if (count($inner_boundary_element_a)) {
        $inner_boundary_element = $inner_boundary_element_a[0];
        foreach ($this->childElements($inner_boundary_element, 'linearring') as $inner_ring_element) {
      	  $components[] = $this->parseLinearRing($inner_ring_element);
        }
      }
      
      return new Polygon($components);
    }
    
    protected function parseGeometryCollection($xml) {
      $components = array();
      $geom_types = geoPHP::geometryList();
      foreach ($xml->childNodes as $child) {
      	$function = 'parse'.$geom_types[$child->nodeName];
        $components[] = $this->$function($child);
      }
      return new GeometryCollection($components);
    }
    
    protected function _extractCoordinates($xml) {
    	$coord_elements = $this->childElements($xml, 'coordinates');
      if (!count($coord_elements)) {
      	throw new Exception('Bad KML: Missing coordinate element');
      }
      $coordinates = array();
      $coord_sets = explode(' ',$coord_elements[0]->nodeValue);
      foreach ($coord_sets as $set_string) {
      	$set_string = trim($set_string);
      	if ($set_string) {
          $set_array = explode(',',$set_string);
          if (count($set_array) >= 2) {
            $coordinates[] = $set_array;
          }
        }
      }
      
      return $coordinates;
    }
    
    private function geometryToKML($geom) {
      $type = strtolower($geom->getGeomType());
      switch ($type) {
          case 'point':
              return $this->pointToKML($geom);
              break;
          case 'linestring':
          case 'linearring':
              return $this->linestringToKML($geom);
              break;
          case 'polygon':
              return $this->polygonToKML($geom);
              break;
          case 'multipoint':
          case 'multilinestring':
          case 'multipolygon':
          case 'geometrycollection':
              return $this->collectionToKML($geom);
              break;
      }
    }

    private function pointToKML($geom) {
      return "<point><coordinates>".$geom->getX().",".$geom->getY()."</coordinates></point>";
    }

    private function linestringToKML($geom) {
      $type = strtolower($geom->getGeomType());
      $str = '<'. $type .'><coordinates>';
      foreach ($geom->getComponents() as $comp) {
        $str .= $comp->getX() .','. $comp->getY();
      }

      return  $str .'</coordinates></'. $type .'>';
    }

    public function polygonToKML($geom) {
      $components = $geom->getComponents();
      $str = '<outerBoundaryIs>' . $this->linestringToKML($components[0]) . '</outerBoundaryIs>';
      foreach (array_slice($components, 1) as $comp) {
        $str .= '<innerBoundaryIs>' . $this->linestringToKML($comp) . '</innerBoundaryIs>';
      }
        
      return '<polygon>'. $str .'</polygon>';
    }
    
    public function collectionToKML($geom) {
      $components = $geom->getComponents();
      $str = '<MultiGeometry>';
      foreach ($geom->getComponents() as $comp) {
        $sub_adapter = new KML();
        $str .= $sub_adapter->write($comp);
      }

      return $str .'</MultiGeometry>';
    }

}
