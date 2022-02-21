<?php

/*
 * This file is part of the colinodell/json5 package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * Based on the official JSON5 implementation for JavaScript (https://github.com/json5/json5)
 *  - (c) 2012-2016 Aseem Kishore and others (https://github.com/json5/json5/contributors)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ColinODell\Json5\Test\Functional;

use ColinODell\Json5\Json5Decoder;
use ColinODell\Json5\SyntaxError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class ParseTest extends TestCase
{
    /**
     * @param string $json
     *
     * @dataProvider dataForTestJsonParsing
     */
    public function testJsonParsing($json)
    {
        $this->assertEquals(json_decode($json), Json5Decoder::decode($json));
        $this->assertSame(json_decode($json, true), Json5Decoder::decode($json, true));
    }

    /**
     * @return array
     */
    public function dataForTestJsonParsing()
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/data/*')->name('*.json');

        $tests = array();
        foreach ($finder as $file) {
            $tests[] = array(file_get_contents($file));
        }

        return $tests;
    }

    /**
     * @param string      $json
     * @param int         $flags
     * @param string      $expected
     * @param string|null $expectedAssoc
     *
     * @dataProvider dataForTestJson5Parsing
     */
    public function testJson5Parsing($json, $flags, $expected, $expectedAssoc = null)
    {
        $this->assertSame($expected, serialize(Json5Decoder::decode($json, false, 512, $flags)));

        if ($expectedAssoc !== null) {
            $this->assertSame($expectedAssoc, serialize(Json5Decoder::decode($json, true, 512, $flags)));
        }
    }

    /**
     * @return array
     */
    public function dataForTestJson5Parsing()
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/data/*')->name('*.json5');

        $tests = array();
        foreach ($finder as $file) {
            $data = explode('////////// EXPECTED OUTPUT: //////////', file_get_contents($file));
            $firstLine = preg_split('#\r?\n#', $data[0], 0)[0];
            if (str_starts_with($firstLine, '//@flags:')) {
                $flags = eval('return ' . trim(substr($firstLine, strlen('//@flags:'))) . ';');
            } else {
                $flags = 0;
            }

            $tests[] = array(
                $data[0],
                $flags,
                trim($data[1]),
                isset($data[2]) ? trim($data[2]) : null,
            );
        }

        return $tests;
    }

    /**
     * @param string $json
     *
     * @dataProvider dataForTestValidES5DisallowedByJson5
     */
    public function testValidES5DisallowedByJson5($json)
    {
        $this->expectException(SyntaxError::class);

        Json5Decoder::decode($json);
    }

    /**
     * @return array
     */
    public function dataForTestValidES5DisallowedByJson5()
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/data/*')->name('*.js');

        $tests = array();
        foreach ($finder as $file) {
            $tests[] = array(file_get_contents($file));
        }

        return $tests;
    }

    /**
     * @param string $json
     * @param array  $expectedError
     *
     * @dataProvider dataForTestInvalidES5WhichIsAlsoInvalidJson5
     */
    public function testInvalidES5WhichIsAlsoInvalidJson5($json, $expectedError)
    {
        try {
            Json5Decoder::decode($json);
            $this->fail('Invalid ES5/JSON5 should fail');
        } catch (SyntaxError $e) {
            if ($expectedError !== null) {
                $this->assertEquals($expectedError['lineNumber'], $e->getLineNumber());
                $this->assertEquals($expectedError['columnNumber'], $e->getColumn());
                $this->assertStringStartsWith($expectedError['message'], $e->getMessage());
            }

            return $this->assertTrue(true);
        }

        $this->fail('Invalid ES5/JSON5 should fail');
    }

    /**
     * @return array
     */
    public function dataForTestInvalidES5WhichIsAlsoInvalidJson5()
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/data/*')->name('*.txt');

        $tests = array();
        foreach ($finder as $file) {
            $tests[] = array(
                file_get_contents($file),
                $this->getErrorSpec($file),
            );
        }

        return $tests;
    }

    public function testNaNWithSign()
    {
        $this->assertTrue(is_nan(Json5Decoder::decode('+NaN')));
    }

    public function testBadNumberStartingWithN()
    {
        $this->expectException(SyntaxError::class);

        Json5Decoder::decode('NotANumber');
    }

    public function testBadNumberStartingWithI()
    {
        $this->expectException(SyntaxError::class);

        Json5Decoder::decode('+Indigo');
    }

    public function testNonBreakingSpaceInISO8859()
    {
        $this->assertSame(3, Json5Decoder::decode(chr(0xA0) . ' 3 '));
    }

    public function testNonBreakingSpaceInUTF8()
    {
        $this->assertSame(3, Json5Decoder::decode(chr(0xC2) . chr(0xA0) . ' 3 '));
    }

    public function testExceptionType()
    {
        try {
            Json5Decoder::decode('{');
            $this->fail('Exception should have been thrown');
        } catch (\Exception $ex) {
            $this->assertTrue(is_subclass_of($ex, 'JsonException'));
            $this->assertSame('ColinODell\\Json5\\SyntaxError', get_class($ex));
        }
    }

    public function testFunctionAlias()
    {
        $json = json_encode(['hello' => 'world']);
        $this->assertEquals(Json5Decoder::decode($json, true), json5_decode($json, true));
    }

    /**
     * Regression: Parsing JSON5 always throws JsonException when JSON_THROW_ON_ERROR is set by the caller
     *
     * @see https://github.com/colinodell/json5/issues/15
     */
    public function testExceptionShouldNotBeThrownOnValidJSON5()
    {
        $ret = Json5Decoder::decode('"foo" // simple string with a comment', false, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('foo', $ret);
    }

    private function getErrorSpec($file)
    {
        $errorSpec = str_replace('.txt', '.errorSpec', $file);
        if (!file_exists($errorSpec)) {
            return null;
        }

        $spec = json_decode(file_get_contents($errorSpec), true);

        return $spec;
    }
}
