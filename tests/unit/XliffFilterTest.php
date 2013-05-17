<?php
require_once("XliffFilter.php");

/**
 * @covers XliffFilter
 */
class XliffFilterTest extends PHPUnit_Framework_TestCase
{	
	private $filter;
	
	public function setUp()
	{
		$this->filter = new XliffFilter();
		$this->filter->updateCurrentXliffTag('ph', 1);
	}
	
	public function tearDown()
	{
		
	}

	public function provideStrings()
	{
		return array(
			array('<table>
	<thead>
		<tr>
			<th>Lorem <b>ipsum</b></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>Lorem ipsum</td>
		</tr>
	</tbody>
</table>', 5),
			array('<ul>
   <li>Lorem ipsum dolor sit <a href="#">amet</a>, consectetuer adipiscing elit.</li>
   <li>Aliquam tincidunt mauris eu risus.</li>
   <li>Vestibulum auctor dapibus neque.</li>
</ul>', 7),
			array('<h1>HTML Ipsum Presents</h1>
<p><strong>Pellentesque habitant morbi tristique</strong> senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. <em>Aenean ultricies mi vitae est.</em> Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed, <code>commodo vitae</code>, ornare sit amet, wisi. Aenean fermentum, elit eget tincidunt condimentum, eros ipsum rutrum orci, sagittis tempus lacus enim ac dui. <a href="#">Donec non enim</a> in turpis pulvinar facilisis. Ut felis.</p>', 5) );
	}
	
	/**
	 * @covers updateCurrentXliffTag
	 */
	public function testUpdateCurrentXliffTag()
	{

		$this->assertEquals('ph', $this->filter->getCurrentXliffTagName());
		$this->assertEquals(1, $this->filter->getCurrentXliffTagId());
	}
	
	/**
	 * @covers getOpeningXliffTag
	 * @depends testUpdateCurrentXliffTag
	 */
	public function testGetOpeningXliffTag()
	{
		$this->assertEquals('<ph id="1">', $this->filter->getOpeningXliffTag());
	}
	
	/**
	 * @covers getClosingXliffTag
	 * @depends testUpdateCurrentXliffTag
	 */
	public function testGetClosingXliffTag()
	{
		$this->assertEquals('</ph>', $this->filter->getClosingXliffTag());
	}
	/**
	 * @covers extractElementName
	 */
	public function testExtractElementName()
	{
		$this->assertEquals('html', $this->filter->extractElementName('<html>'));
		$this->assertEquals('placeholder', $this->filter->extractElementName('{placeholder}'));
		$this->assertEquals('1', $this->filter->extractElementName('%1'));
		$this->assertEquals('2', $this->filter->extractElementName('%2%'));
		$this->assertEmpty($this->filter->extractElementName('Unrecognized Tag'));
	}
	
	/**
	 * @covers segment
	 * @dataProvider provideStrings
	 */
	public function testSegment($string, $segments)
	{
		$this->filter->segment($string);
		print_r($this->filter->getTranslationUnits());
		$this->assertEquals($segments, count($this->filter->getTranslationUnits()));
	}
}

?>
