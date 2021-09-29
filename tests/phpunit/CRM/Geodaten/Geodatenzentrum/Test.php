<?php

require_once (__DIR__ . '/../../../../../CRM/Geodaten/Geodatenzentrum.php');

class CRM_Geodaten_Geodatenzentrum_Test extends \PHPUnit\Framework\TestCase {

  /**
   * @var CRM_Geodaten_Geodatenzentrum
   *  Geodatenzentrum api interface
   */
  private $geodatenzentrum;

  public function setUp(): void {
    parent::setUp();

    $this->geodatenzentrum = new CRM_Geodaten_Geodatenzentrum();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  public function testGetGeodaten() {
    $data = $this->geodatenzentrum->getGeodata(52.520008, 13.404954);
    $this->assertEquals(array(
      'bundesland' => 'Berlin',
      'rs' => '110000000000',
      'kreis' => 'Berlin',
      'gemeinde' => 'Berlin'
    ), $data);

    $data = $this->geodatenzentrum->getGeodata(50.74532, 7.20224);
    $this->assertEquals(array(
      'bundesland' => 'Nordrhein-Westfalen',
      'rs' => '053820056056',
      'kreis' => 'Rhein-Sieg-Kreis',
      'gemeinde' => 'Sankt Augustin',
      'regierungsbezirk' => 'Köln'
    ), $data);

    $data = $this->geodatenzentrum->getGeodata(49.0100672, 8.4229579);
    $this->assertEquals(array(
      'bundesland' => 'Baden-Württemberg',
      'rs' => '082120000000',
      'kreis' => 'Karlsruhe',
      'gemeinde' => 'Karlsruhe',
      'regierungsbezirk' => 'Karlsruhe'
    ), $data);

    $data = $this->geodatenzentrum->getGeodata(51.041326, 9.8608943);
    $this->assertEquals(array(
      'bundesland' => 'Hessen',
      'rs' => '066320005005',
      'kreis' => 'Hersfeld-Rotenburg',
      'gemeinde' => 'Cornberg',
      'regierungsbezirk' => 'Kassel'
    ), $data);

    $data = $this->geodatenzentrum->getGeodata(50.0432959, 7.8021675);
    $this->assertEquals(array(
      'bundesland' => 'Hessen',
      'rs' => '064390010010',
      'kreis' => 'Rheingau-Taunus-Kreis',
      'gemeinde' => 'Lorch',
      'regierungsbezirk' => 'Darmstadt'
    ), $data);
  }
}
