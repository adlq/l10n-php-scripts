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

	public function segmentProvider()
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
</table>', array(
  '<table>
	<thead>
		<tr>
			<th>',
  'Lorem <b>ipsum</b>',
  '</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>',
  'Lorem ipsum',
  '</td>
		</tr>
	</tbody>
</table>'), array('html')),
			array('<ul>
   <li>Lorem ipsum dolor sit <a href="#">amet</a>, consectetuer adipiscing elit.</li>
   <li>Aliquam tincidunt mauris eu risus.</li>
   <li>Vestibulum auctor dapibus neque.</li>
</ul>', array('<ul>
   <li>',
  'Lorem ipsum dolor sit <a href="#">amet</a>, consectetuer adipiscing elit.',
  '</li>
   <li>',
  'Aliquam tincidunt mauris eu risus.',
  '</li>
   <li>',
  'Vestibulum auctor dapibus neque.',
  '</li>
</ul>')),
			array('<h1>HTML Ipsum Presents</h1>
<p><strong>Pellentesque habitant morbi tristique</strong> senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. <em>Aenean ultricies mi vitae est.</em> Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed, <code>commodo vitae</code>, ornare sit amet, wisi. Aenean fermentum, elit eget tincidunt condimentum, eros ipsum rutrum orci, sagittis tempus lacus enim ac dui. <a href="#">Donec non enim</a> in turpis pulvinar facilisis. Ut felis.</p>',
      array('<h1>HTML Ipsum Presents</h1>
',
        '<p>',
        '<strong>Pellentesque habitant morbi tristique</strong> senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. <em>Aenean ultricies mi vitae est.</em> Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed, <code>commodo vitae</code>, ornare sit amet, wisi. Aenean fermentum, elit eget tincidunt condimentum, eros ipsum rutrum orci, sagittis tempus lacus enim ac dui. <a href="#">Donec non enim</a> in turpis pulvinar facilisis. Ut felis.',
        '</p>')),
      array('<html>
 <head>
  <title>Pellentesque habitant morbi tristique</title>
  <meta name="Pellentesque" content="Aenean ultricies mi vitae est">
 </head>
<body>
<p>Pellentesque habitant morbi tristique.</p>
</body>
</html>', array('<html>
 <head>
  <title>',
  'Pellentesque habitant morbi tristique',
  '</title>
  <meta name="Pellentesque" content="Aenean ultricies mi vitae est">
 </head>
<body>
<p>',
  'Pellentesque habitant morbi tristique.',
  '</p>
</body>
</html>')));
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
    
		$this->assertEmpty($this->filter->extractElementName('Foo bar'));
		$this->assertEmpty($this->filter->extractElementName('{foo'));
		$this->assertEmpty($this->filter->extractElementName('bar}'));
		$this->assertEmpty($this->filter->extractElementName('bar>'));
		$this->assertEmpty($this->filter->extractElementName('<bar'));
	}
	
	/**
	 * @covers segment
	 * @dataProvider segmentProvider
	 */
	public function testSegment($string, $segments)
	{
		$this->filter->segment($string);
		$this->assertEquals($segments, $this->filter->getTranslationUnits());
    
    return "test";
	}
  
  /**
   * @covers buildStacks
   * @dataProvider provideTranslationUnits
   */
  public function testBuildStacks($tu, $fullStack, $verificationStack)
  {
    $this->filter->buildStacks($tu);
    $this->assertEquals($fullStack, $this->filter->getFullStack());
    $this->assertEquals($verificationStack, $this->filter->getVerificationStack());
  }
  
  /**
   * 
   * @covers processStacks
   * @depends testBuildStacks
   * @dataProvider provideTranslationUnits
   */
  public function testProcessStacks($tu, $fullStack, $verificationStack, $processedFullStack)
  {
    $this->filter->buildStacks($tu);
    $this->filter->processStacks($tu);
    $this->assertEquals($processedFullStack, $this->filter->getFullStack());
  }

  public function provideTranslationUnits()
  {
    return array(
      array('<html><head></head><body><p><b>Test</b></p></body></html>', 
        array(
          array('html' => 1),
          array('head' => 2),
          array('head' => -2),
          array('body' => 3),
          array('p' => 4),
          array('b' => 5),
          array('b' => -5),
          array('p' => -4),
          array('body' => -3),
          array('html' => -1)),
        array(),
        array(
          array('html' => 1),
          array('head' => 2),
          array('head' => -2),
          array('body' => 3),
          array('p' => 4),
          array('b' => 5),
          array('b' => -5),
          array('p' => -4),
          array('body' => -3),
          array('html' => -1))),
      array('<p><b>Hello <a href="#">World</a></b></body>',
        array(
          array('p' => 1),
          array('b' => 2),
          array('a' => 3),
          array('a' => -3),
          array('b' => -2),
          array('body' => 0)),
        array(array('p' => 1)),
        array(
          array('p' => 0),
          array('b' => 2),
          array('a' => 3),
          array('a' => -3),
          array('b' => -2),
          array('body' => 0))),
      array('<p>Hello {adjective} %1 World</p>', 
        array(
          array('p' => 1),
          array('adjective' => 2),
          array('1' => 3), 
          array('p' => -1)),
        array(), 
        array(
          array('p' => 1),
          array('adjective' => 0),
          array('1' => 0),
          array('p' => -1)))
    );
  }
  
  /**
   * #
   * @dataProvider processStringProvider
   */
  public function testProcessString($raw, $segments, $stack, $formatted)
  {
    $this->filter->segment($raw);
    $this->filter->setFullStack($stack);
    
		// Finally, apply the xliff tags to the string
    $this->assertEquals($formatted, $this->filter->processString($raw));
  }
  
  public function processStringProvider()
  {
    return array(
      array(
        '<p>Foo {bar} <b><a href="#">bar</a></b><p>',
        array('<p>', 'Foo {bar} <b><a href="#">bar</a></b>', '</p>'),
        array(
          array('p' => 1),
          array('bar' => 0),
          array('b' => 2),
          array('a' => 3),
          array('a' => -3),
          array('b' => -2),
          array('p' => -1)
        ),
        '<bpt id="1"><p></bpt>Foo <ph id="1">{bar}</ph> <bpt id="2"><b></bpt><bpt id="3"><a href="#"></bpt>bar<ept id="3"></a></ept><ept id="2"></b></ept><ept id="1"><p></ept>')
    );
  }
}

?>
