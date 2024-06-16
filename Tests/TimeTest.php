<?php
/**
 * Time Enum Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Cache\Data\Time;

class TimeTest extends TestCase {
	/** @var array<string,int> */
	private static array $times = array(
		'Second' => 1,
		'Minute' => 60,
		'Hour'   => 60 * 60,
		'Day'    => 60 * 60 * 24,
		'Week'   => 60 * 60 * 24 * 7,
		'Month'  => 60 * 60 * 24 * 30,
		'Year'   => 60 * 60 * 24 * 365,
	);

	public function testCalculationInSeconds(): void {
		foreach ( Time::cases() as $time ) {
			$this->assertSAme( self::$times[ $time->name ], $time->value );
		}
	}

	/** @dataProvider provideAdditionTime */
	public function testAddition( int $expected, Time $time, float $add, ?Time $from ): void {
		$this->assertSame( $expected, $time->add( $add, $from ) );
	}

	public function provideAdditionTime(): array {
		return array(
			array( 11, Time::Second, 10, null ),
			array( 1860, Time::Minute, 0.5, Time::Hour ),
			array( 181, Time::Second, 3, Time::Minute ),
			array( ( self::$times['Month'] + ( self::$times['Year'] * 7 ) ), Time::Month, 7, Time::Year ),
			array( self::$times['Week'] + 180, Time::Week, 3, Time::Minute ),
		);
	}

	/** @dataProvider provideSubtractionTime */
	public function testSubtraction( int $expected, Time $time, float $sub, ?Time $from ): void {
		$this->assertSame( $expected, $time->reduce( $sub, $from ) );
	}

	public function provideSubtractionTime(): array {
		return array(
			array( 60, Time::Minute, 0, null ),
			array( 30, Time::Minute, 0.5, null ),
			array( 25, Time::Minute, 35, Time::Second ),
			array( self::$times['Hour'] - 180, Time::Hour, 3, Time::Minute ),
			array( self::$times['Month'] - 120, Time::Month, 2, Time::Minute ),
			array( ( self::$times['Year'] - ( self::$times['Month'] * 3 ) ), Time::Year, 3, Time::Month ),
		);
	}

	/** @dataProvider provideMultiplicationTime */
	public function testMultiplication( int $expected, Time $time, float $x ): void {
		$this->assertSame( $expected, $time->expand( $x ) );
	}

	public function provideMultiplicationTime(): array {
		return array(
			array( 10, Time::Second, 10 ),
			array( 20 /* 19.8 */, Time::Minute, 0.33 ),
			array( 21 /* 20.04 */, Time::Minute, 0.334 ),
			array( 1800, Time::Hour, 0.5 ),
			array( self::$times['Day'] * 3, Time::Day, 3 ),
			array( ( self::$times['Year'] * 7 ), Time::Year, 7 ),
		);
	}

	/** @dataProvider provideDivisionTime */
	public function testDivision( int $expected, Time $time, float $d ): void {
		$this->assertSame( $expected, $time->split( $d ) );
	}

	public function provideDivisionTime(): array {
		return array(
			array( 30, Time::Minute, 2 ),
			array( 20 /* 20.76 */, Time::Minute, 2.89 ),
			array( 7200, Time::Hour, 0.5 ),
			array( 1200, Time::Hour, 3 ),
			array( self::$times['Day'] / 4, Time::Day, 4 ),
		);
	}

	public function testAdditionThrowsException(): void {
		$this->assertInvalidTimeException();
		Time::Second->add( extra: -1 );
	}

	/** @dataProvider provideSubtractionResultingInLessThanOne */
	public function testSubtractionThrowsException( Time $time, int $sub, ?Time $from ): void {
		$this->assertInvalidTimeException();
		$time->reduce( $sub, $from );
	}

	public function provideSubtractionResultingInLessThanOne(): array {
		return array(
			array( Time::Second, 1, null ),
			array( Time::Minute, 1, Time::Minute ),
		);
	}

	public function testMultiplicationThrowsException(): void {
		$this->assertInvalidTimeException();
		Time::Second->expand( by: -2 );
	}

	public function testDivisionThrowsException(): void {
		$this->assertInvalidTimeException();
		Time::Minute->split( by: -5 );
	}

	/** @dataProvider provideTimeWithIntervals */
	public function testGetInterval( string $expected, Time $time ): void {
		$this->assertSame( $expected, actual: $time->getInterval() );
	}

	/** @return array<array{0:string,1:Time}> */
	public function provideTimeWithIntervals(): array {
		return array(
			array( '1 second', Time::Second ),
			array( '1 minute', Time::Minute ),
			array( '1 hour', Time::Hour ),
			array( '1 day', Time::Day ),
			array( '1 week', Time::Week ),
			array( '1 month', Time::Month ),
			array( '1 year', Time::Year ),
		);
	}

	private function assertInvalidTimeException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot set cache time to less than a second.' );
	}
}
