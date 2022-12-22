<?php declare(strict_types=1);

namespace DDT\Test\Helpers;

use DDT\Exceptions\ArrayCollection\ArrayCollectionInvalidKeyException;
use DDT\Exceptions\ArrayCollection\ArrayCollectionKeyNotExistsException;
use DDT\Helper\ArrayCollection;
use PHPUnit\Framework\TestCase;

class ArrayCollectionTest extends TestCase
{
    public function testCreateEmptyCollection()
    {
        $a = new ArrayCollection();

        $this->assertInstanceOf(ArrayCollection::class, $a);

        $this->assertCount(0, $a);
        $this->assertEquals(0, $a->count());

        $this->assertEquals([], $a->toArray());
        $this->assertFalse($a->first());
        $this->assertFalse($a->last());
        $this->assertFalse($a->isAssoc());
    }

    public function testBasicAssocArrayFunctionality()
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];

        $a = new ArrayCollection($data);

        $this->assertInstanceOf(ArrayCollection::class, $a);
        $this->assertTrue($a->isAssoc());

        $this->assertEquals($data, $a->getData());

        $this->assertEquals(1, $a['a']);
        $this->assertEquals(2, $a->get('b'));
        $this->assertEquals('a', $a->key());
        $a->next();
        $this->assertEquals(2, $a->current());
        $this->assertEquals('b', $a->key());
        $a->next();
        $this->assertEquals(3, $a->current());
        $this->assertEquals(3, $a->count());
        $this->assertEquals(1, $a->first());
        $this->assertEquals(3, $a->last());
        $this->assertEquals('c', $a->key());
        $this->assertEquals(1, $a->reset());

        $this->assertCount(3, $a);
        $this->assertEquals(3, $a->count());
    }

    /**
     * @return void
     * @throws ArrayCollectionInvalidKeyException
     * @throws \DDT\Exceptions\ArrayCollection\ArrayCollectionKeyNotExistsException
     */
    public function testDynamicallyUpdatingFunctionality()
    {
        $a = new ArrayCollection();
        $a[] = "feature";
        $a->set(10, "is");
        $a["something"] = "that";
        $a->set("we", "must");
        $a->add("test");
        $a->unshift("this");

        $this->assertEquals(0, $a->key());
        $this->assertEquals('this', $a[0]);
        $this->assertEquals('this', $a->get(0));
        $this->assertEquals('this', $a->first());

        // we should find six elements
        $this->assertCount(6, $a);
        $this->assertEquals(6, $a->count());

        // remove the first element
        $this->assertEquals('this', $a->shift());

        // now we should only find 5
        $this->assertCount(5, $a);
        $this->assertEquals(5, $a->count());

        // add the element back, then test we get six and the first key is still 0
        $this->assertCount(6, $a->unshift('this'));
        $this->assertEquals(0, $a->key());
        $this->assertEquals('this', $a->first());

        // a->set(10, 'is') because 10 is an integer it will be
        // ignored and appended to the end instead
        // meaning the index will be 2 and not 10
        $this->assertEquals('is', $a->get(2));
        $this->assertEquals('is', $a[2]);

        $this->assertEquals('that', $a['something']);
        $a->next();
        $this->assertEquals('feature', $a->current());
        $this->assertEquals(3, $a->find('test'));
    }

    public function testSetEmptyKeyThrowException()
    {
        $this->expectException(ArrayCollectionInvalidKeyException::class);
        $this->expectExceptionMessage(ArrayCollectionInvalidKeyException::EMPTY_KEY);
        $a = new ArrayCollection();
        $a->set(null, 'something');
    }

    public function testSetNonScalarKeyThrowException()
    {
        $this->expectException(ArrayCollectionInvalidKeyException::class);
        $this->expectExceptionMessage(ArrayCollectionInvalidKeyException::NON_SCALAR_KEY);
        $a = new ArrayCollection();
        $a[new \stdClass()] = [];
    }

    public function testGetNonExistingKeyKeyThrowException()
    {
        $k = 'monkey';
        $e = new ArrayCollectionKeyNotExistsException($k);

        $this->expectException(ArrayCollectionKeyNotExistsException::class);
        $this->expectExceptionMessage($e->getMessage());

        $a = new ArrayCollection(['test', 'data']);
        $test = $a[$k];
    }

    /**
     * @return void
     * @throws ArrayCollectionKeyNotExistsException
     */
    public function testGetVariousKeys()
    {
        $a = new ArrayCollection([
            'first' => [
                'second' => [
                    'third' => ['fourth', 'fifth', 'sixth'],
                ],
                'seventh' => true,
                'eighth' => false,
            ]
        ]);

        // access the numerical indexes as part of a key path
        $this->assertEquals('sixth', $a->get('first.second.third.2'));
        $this->assertEquals(false, $a->get('first.eighth'));

        // dynamically set a new element which has a numerical index
        $a->set('first.nineth', ['tenth' => [99, 88, 77]]);
        $this->assertEquals(77, $a->get('first.nineth.tenth.2'));

        // retrieve deeper than a numerical index
        $a->set('eleventh.twelveth', ['thirteen', ['fifteen' => 'monkey']]);
        $this->assertEquals('monkey', $a->get('eleventh.twelveth.1.fifteen'));
    }
}