<?php

namespace Sabre\CalDAV;

use Sabre\DAV;
use Sabre\DAVACL;
use Sabre\HTTP;

require_once 'Sabre/HTTP/ResponseMock.php';

class ValidateICalTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Sabre\DAV\Server
     */
    protected $server;
    /**
     * @var Sabre\CalDAV\Backend\Mock
     */
    protected $calBackend;

    function setUp() {

        $calendars = array(
            array(
                'id' => 'calendar1',
                'principaluri' => 'principals/admin',
                'uri' => 'calendar1',
                '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new Property\SupportedCalendarComponentSet( array('VEVENT','VTODO','VJOURNAL') ),
            ),
            array(
                'id' => 'calendar2',
                'principaluri' => 'principals/admin',
                'uri' => 'calendar2',
                '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new Property\SupportedCalendarComponentSet( array('VTODO','VJOURNAL') ),
            )
        );

        $this->calBackend = new Backend\Mock($calendars,array());
        $principalBackend = new DAVACL\PrincipalBackend\Mock();

        $tree = array(
            new CalendarRootNode($principalBackend, $this->calBackend),
        );

        $this->server = new DAV\Server($tree);
        $this->server->debugExceptions = true;

        $plugin = new Plugin();
        $this->server->addPlugin($plugin);

        $response = new HTTP\ResponseMock();
        $this->server->httpResponse = $response;

    }

    function request(HTTP\Request $request) {

        $this->server->httpRequest = $request;
        $this->server->exec();

        return $this->server->httpResponse;

    }

    function testCreateFile() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));

        $response = $this->request($request);

        $this->assertEquals('415 Unsupported Media Type', $response->status);

    }

    function testCreateFileValid() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('201 Created', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);
        $this->assertEquals(array(
            'Content-Length' => '0',
            'ETag' => '"' . md5("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n") . '"',
        ), $response->headers);
        $expected = array(
            'uri'          => 'blabla.ics',
            'calendardata' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'calendarid'   => 'calendar1',
        );

        $this->assertEquals($expected, $this->calBackend->getCalendarObject('calendar1','blabla.ics'));

    }

    function testCreateFileNoComponents() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('400 Bad request', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testCreateFileNoUID() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('400 Bad request', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testCreateFileVCard() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCARD\r\nEND:VCARD\r\n");

        $response = $this->request($request);

        $this->assertEquals('415 Unsupported Media Type', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testCreateFile2Components() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nBEGIN:VJOURNAL\r\nUID:foo\r\nEND:VJOURNAL\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('400 Bad request', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testCreateFile2UIDS() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VTIMEZONE\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:bar\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('400 Bad request', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testCreateFileWrongComponent() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VTIMEZONE\r\nEND:VTIMEZONE\r\nBEGIN:VFREEBUSY\r\nUID:foo\r\nEND:VFREEBUSY\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('400 Bad request', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testUpdateFile() {

        $this->calBackend->createCalendarObject('calendar1','blabla.ics','foo');
        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));

        $response = $this->request($request);

        $this->assertEquals('415 Unsupported Media Type', $response->status);

    }

    function testUpdateFileParsableBody() {

        $this->calBackend->createCalendarObject('calendar1','blabla.ics','foo');
        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $body = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $request->setBody($body);

        $response = $this->request($request);

        $this->assertEquals('204 No Content', $response->status);

        $expected = array(
            'uri'          => 'blabla.ics',
            'calendardata' => $body,
            'calendarid'   => 'calendar1',
        );

        $this->assertEquals($expected, $this->calBackend->getCalendarObject('calendar1','blabla.ics'));

    }

    function testCreateFileInvalidComponent() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar2/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VTIMEZONE\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('403 Forbidden', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    function testUpdateFileInvalidComponent() {

        $this->calBackend->createCalendarObject('calendar2','blabla.ics','foo');
        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar2/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VTIMEZONE\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nUID:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('403 Forbidden', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);

    }

    /**
     * What we are testing here, is if we send in a latin1 character, the
     * server should automatically transform this into UTF-8.
     *
     * More importantly. If any transformation happens, the etag must no longer
     * be returned by the server.
     */
    function testCreateFileModified() {

        $request = HTTP\Request::createFromServerArray(array(
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/calendars/admin/calendar1/blabla.ics',
        ));
        $request->setBody("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:foo\r\nSUMMARY:Meeting in M\xfcnster\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $response = $this->request($request);

        $this->assertEquals('201 Created', $response->status, 'Incorrect status returned! Full response body: ' . $response->body);
        $this->assertFalse(isset($response->headers['ETag']));

    }
}
