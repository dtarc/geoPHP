<?php
/*
 * (c) Camptocamp <info@camptocamp.com>
 * (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Point : a Point geometry.
 *
 * @package    sfMapFishPlugin
 * @subpackage GeoJSON
 * @author     Camptocamp <info@camptocamp.com>
 * @version
 */
class Point extends Geometry
{
  private $position = array(2);

  protected $geom_type = 'Point';

  /**
   * Constructor
   *
   * @param float $x The x coordinate (or longitude)
   * @param float $y The y coordinate (or latitude)
   */
  public function __construct($x, $y)
  {
    if (!is_numeric($x) || !is_numeric($y))
    {
      throw new Exception("Bad coordinates: x and y should be numeric");
    }
    $this->position = array($x, $y);
  }

  /**
   * An accessor method which returns the coordinates array
   *
   * @return array The coordinates array
   */
  public function getCoordinates()
  {
    return $this->position;
  }

  /**
   * Returns X coordinate of the point
   *
   * @return integer The X coordinate
   */
  public function getX()
  {
    return $this->position[0];
  }

  /**
   * Returns X coordinate of the point
   *
   * @return integer The X coordinate
   */
  public function getY()
  {
    return $this->position[1];
  }
  
  
  // A point's centroid is itself
  public function getCentroid()
  {
    return $this; 
  }
  
  public function getBBox() {
    return array(
      'maxy' => $this->getY(),
      'miny' => $this->getY(),
      'maxx' => $this->getX(),
      'minx' => $this->getX(),
    );
  }

  public function getArea() {
    return 0;
  }

  public function intersects($distance) {
    //TODO
  }

}

