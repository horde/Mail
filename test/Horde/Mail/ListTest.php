<?php
/**
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mail
 * @subpackage UnitTests
 */
namespace Horde\Mail;
use \Horde_Mime_Headers_Addresses;
use PHPUnit\Framework\TestCase;
use \Horde_Mail_Rfc822_Identification;
use \Horde_Mail_Rfc822;
use \Horde_Mail_Rfc822_List;
use \Horde_Mail_Rfc822_Group;
use \Horde_Mail_Rfc822_Address;

class ListTest extends TestCase
{
    private $rfc822;

    public function setUp(): void
    {
        $this->rfc822 = new Horde_Mail_Rfc822();
    }

    public function testSingleAddress()
    {
        $email = 'Test <test@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $this->assertEquals(
            1,
            count($res)
        );

        $expected = array($email);

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }

        $this->assertEquals(
            $expected,
            $res->addresses
        );

        $expected = array(
            'test@example.com'
        );

        $this->assertEquals(
            $expected,
            $res->bare_addresses
        );

        $this->assertEquals(
            0,
            $res->groupCount()
        );
    }

    public function testSingleAddressWithFilter()
    {
        $email = 'Test <test@example.com>';

        $res = $this->rfc822->parseAddressList($email);
        $res->setIteratorFilter(0, array('test@example.com'));

        $this->assertEquals(
            0,
            count($res)
        );

        foreach ($res as $val) {
            $this->fail('Results should be empty.');
        }

        $this->assertEquals(
            array(),
            $res->addresses
        );

        $this->assertEquals(
            array(),
            $res->bare_addresses
        );

        $this->assertEquals(
            0,
            $res->groupCount()
        );
    }

    public function testSimpleAddressList()
    {
        $email = 'Test <test@example.com>, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $this->assertEquals(
            2,
            count($res)
        );

        $expected = array(
            'Test <test@example.com>',
            'Test2 <test2@example.com>'
        );

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }

        $this->assertEquals(
            $expected,
            $res->addresses
        );

        $expected = array(
            'test@example.com',
            'test2@example.com'
        );

        $this->assertEquals(
            $expected,
            $res->bare_addresses
        );

        $this->assertEquals(
            0,
            $res->groupCount()
        );
    }

    public function testSeekingInList()
    {
        $email = 'Test <test@example.com>, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $this->expectException('OutOfBoundsException');
        $res->seek(1);

        $this->assertEquals(
            'test2',
            $res->current()->mailbox
        );

        // Seek to current pointer value.
        $res->seek(1);
        $this->assertEquals(
            'test2',
            $res->current()->mailbox
        );

        $res->seek(0);

        $this->assertEquals(
            'test',
            $res->current()->mailbox
        );

        $res->seek(2);
    }

    public function testArraySet()
    {
        $email = 'Test <test@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $res[0] = 'Test2 <test2@example.com>';

        $this->assertTrue(isset($res[0]));
        $this->assertEquals(
            1,
            count($res)
        );

        $this->assertEquals(
            'test2',
            $res[0]->mailbox
        );
    }

    public function testArrayUnset()
    {
        $email = 'Test <test@example.com>, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        unset($res[0]);

        $this->assertEquals(
            1,
            count($res)
        );

        $this->assertEquals(
            'test2',
            $res[0]->mailbox
        );
    }

    public function testSimpleAddressListWithFilter()
    {
        $email = 'Test <test@example.com>, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);
        $res->setIteratorFilter(0, array('test@example.com'));

        $this->assertEquals(
            1,
            count($res)
        );

        $expected = array(
            'Test2 <test2@example.com>'
        );

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }

        $this->assertEquals(
            $expected,
            $res->addresses
        );

        $expected = array(
            'test2@example.com'
        );

        $this->assertEquals(
            $expected,
            $res->bare_addresses
        );

        $this->assertEquals(
            0,
            $res->groupCount()
        );
    }

    public function testAddressListWithGroup()
    {
        $email = 'Test <test@example.com>, Group: foo@example.com, Foo 2 <foo2@example.com>;, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $this->assertEquals(
            4,
            count($res)
        );

        $expected = array(
            'Test <test@example.com>',
            'Group: foo@example.com, Foo 2 <foo2@example.com>;',
            'foo@example.com',
            'Foo 2 <foo2@example.com>',
            'Test2 <test2@example.com>'
        );

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }

        unset($expected[1]);

        $this->assertEquals(
            array_values($expected),
            $res->addresses
        );

        $expected = array(
            'test@example.com',
            'foo@example.com',
            'foo2@example.com',
            'test2@example.com'
        );

        $this->assertEquals(
            $expected,
            $res->bare_addresses
        );

        $this->assertEquals(
            1,
            $res->groupCount()
        );
    }

    public function testAddressListWithGroupWithFilter()
    {
        $email = 'Test <test@example.com>, Group: foo@example.com, Foo 2 <foo2@example.com>;, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);
        $res->setIteratorFilter(0, array('foo@example.com'));

        $this->assertEquals(
            3,
            count($res)
        );

        $expected = array(
            'Test <test@example.com>',
            'Group: foo@example.com, Foo 2 <foo2@example.com>;',
            'Foo 2 <foo2@example.com>',
            'Test2 <test2@example.com>'
        );

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }

        unset($expected[1]);

        $this->assertEquals(
            array_values($expected),
            $res->addresses
        );

        $expected = array(
            'test@example.com',
            'foo2@example.com',
            'test2@example.com'
        );

        $this->assertEquals(
            $expected,
            $res->bare_addresses
        );

        $res->setIteratorFilter(Horde_Mail_Rfc822_List::HIDE_GROUPS);

        $expected = array(
            'Test <test@example.com>',
            'foo@example.com',
            'Foo 2 <foo2@example.com>',
            'Test2 <test2@example.com>'
        );

        foreach ($res as $key => $val) {
            $this->assertEquals(
                $expected[$key],
                strval($val)
            );
        }
    }

    public function testRemove()
    {
        $email = 'Test <test@example.com>, Group: foo@example.com, Foo 2 <foo2@example.com>;, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $res_clone = clone $res;
        $res_clone->remove('test2@example.com');

        $this->assertEquals(
            3,
            count($res_clone)
        );

        $res_clone = clone $res;
        $res_clone->remove('foo@example.com');

        $this->assertEquals(
            4,
            count($res_clone)
        );
    }

    public function testUnique()
    {
        $email = 'Test <test@example.com>, test@example.com';

        $res = $this->rfc822->parseAddressList($email);
        $res->unique();

        $this->assertEquals(
            1,
            count($res)
        );
        $this->assertEquals(
            'Test',
            $res[0]->personal
        );

        $email = 'test@example.com, Test <test@example.com>, test2@example.com';

        $res = $this->rfc822->parseAddressList($email);
        $res->unique();

        $this->assertEquals(
            2,
            count($res)
        );
        $this->assertEquals(
            'Test',
            $res[0]->personal
        );
    }

    public function testAddressesProperties()
    {
        $email = 'Test <test@example.com>, Group: foo@example.com, Foo 2 <foo2@example.com>;, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $ob = $res->addresses;
        $this->assertEquals(
            4,
            count($ob)
        );
        $this->assertIsString($ob[0]);

        $ob = $res->bare_addresses;
        $this->assertEquals(
            4,
            count($ob)
        );
        $this->assertIsString($ob[0]);

        $ob = $res->base_addresses;
        $this->assertEquals(
            3,
            count($ob)
        );
        $this->assertTrue($ob[0] instanceof Horde_Mail_Rfc822_Address);
        $this->assertTrue($ob[1] instanceof Horde_Mail_Rfc822_Group);

        $ob = $res->raw_addresses;
        $this->assertEquals(
            4,
            count($ob)
        );
        $this->assertTrue($ob[0] instanceof Horde_Mail_Rfc822_Address);
        $this->assertTrue($ob[1] instanceof Horde_Mail_Rfc822_Address);
    }

    public function testIterateEmptyArray()
    {
        $ob = new Horde_Mail_Rfc822_List();

        foreach ($ob as $val) {
            $this->fail('Nothing to iterate.');
        }

        $ob = new Horde_Mail_Rfc822_List(
            new Horde_Mail_Rfc822_Group()
        );
        $ob->setIteratorFilter(Horde_Mail_Rfc822_List::HIDE_GROUPS);

        foreach ($ob as $val) {
            $this->fail('Nothing to iterate.');
        }

        $this->markTestSkipped('No Exception expected.');
    }

    public function testContains()
    {
        $email = 'Test <test@example.com>, Group: foo@example.com, Foo 2 <foo2@example.com>;, Test2 <test2@example.com>';

        $res = $this->rfc822->parseAddressList($email);

        $this->assertTrue($res->contains('test@example.com'));
        $this->assertTrue($res->contains('foo2@example.com'));
        $this->assertFalse($res->contains('foo4@example.com'));
    }

    /**
     * @dataProvider matchProvider
     */
    public function testMatch($compare, $result)
    {
        $ob = new Horde_Mail_Rfc822_List(array(
            'foo@example.com',
            'bar@example.com'
        ));

        if ($result) {
            $this->assertTrue($ob->match($compare));
        } else {
            $this->assertFalse($ob->match($compare));
        }
    }

    public function matchProvider()
    {
        return array(
            array(array('foo@example.com'), false),
            array(array('bar@example.com'), false),
            array(array('foo@example.com', 'bar@example.com'), true),
            array(array('bar@example.com', 'foo@example.com'), true)
        );
    }

    public function testSerialization()
    {
        $ob = new Horde_Mail_Rfc822_List('foo@example.com');

        $ob2 = unserialize(serialize($ob));

        $this->assertEquals(
            1,
            count($ob2)
        );

        $this->assertEquals(
            'foo',
            $ob[0]->mailbox
        );
    }

    public function testAdd()
    {
        $list = new Horde_Mail_Rfc822_List();

        $headerAddressList = new Horde_Mime_Headers_Addresses(
            "Bcc",
            "firstname lastname <firstname.lastname@horde.org>"
        );

        $list->add($headerAddressList);

        $this->assertSame(
            $headerAddressList->getAddressList()[0]->__toString(),
            $list[0]->__toString()
        );
    }

}
