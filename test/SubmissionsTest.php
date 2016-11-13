<?php

use \Mini\Model\{PingablePDO, Submissions};

/**
 * @coversDefaultClass \Mini\Model\Submissions
 */
class SubmissionsTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Submissions
     */
    private $submissions;

    /**
     * @var int
     */
    const pageSize = 100;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $this->getConnection();
        create_tables(self::$pdo);

        parent::__construct();
    }

    public function getConnection(): PHPUnit_Extensions_Database_DB_IDatabaseConnection
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = create_pdo($GLOBALS);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit_Extensions_Database_DataSet_IDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->submissions = new Submissions(self::$pdo, self::pageSize);
        parent::setUp();
    }

    /**
     * @covers ::getSubmissions
     */
    public function testGetSubmissions()
    {
        $this->assertEquals(count($this->submissions->getSubmissions()), $this->getConnection()->getRowCount('submissions'), "Not an empty array with no submissions");

        $this->submissions->append("test", "lorem ipsum", Submissions::SUBMISSION);
        $this->submissions->append("nightboot", "1", Submissions::CORRECTION);
        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Test setup failed");

        $submissions = $this->submissions->getSubmissions();

        $this->assertCount($this->getConnection()->getRowCount('submissions'), $submissions);

        foreach($submissions as $submission) {
            $this->assertObjectHasAttribute("name", $submission);
            $this->assertObjectHasAttribute("description", $submission);
            $this->assertObjectHasAttribute("date", $submission);
            $this->assertGreaterThanOrEqual(strtotime($submission->date), time());
            $this->assertObjectHasAttribute("id", $submission);
            $this->assertObjectHasAttribute("offline", $submission);
            $this->assertObjectHasAttribute("online", $submission);
            $this->assertObjectHasAttribute("ismod", $submission);
        }

        // Sort order is descending by timestamp
        //TODO test array?
    }

    /**
     * @uses \Mini\Model\Submissions::append
     * @uses \Mini\Model\Submissions::getSubmissions
     */
    public function testGetLastSubmissionsUpdate()
    {
        $this->submissions->append("test", "1", Submissions::CORRECTION);

        $submissions = $this->submissions->getSubmissions();
        $lastModified = $this->submissions->getLastUpdate();

        $this->assertEquals($lastModified, strtotime($submissions[0]->date));
    }
}
