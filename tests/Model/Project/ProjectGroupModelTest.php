<?php declare(strict_types=1);

namespace DDT\Test\Model\Project;

use DDT\Model\Project\ProjectGroupModel;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProjectGroupModelTest extends TestCase
{    
    public function testConstructorDoesNotThrowExceptions(): void
    {
        try{
            $group = new ProjectGroupModel('test-group');
            $group = new ProjectGroupModel($group);
            $group = new ProjectGroupModel(null);
            $group = new ProjectGroupModel(['a', 'b']);
            $group = new ProjectGroupModel('a,b,c');
            $this->assertTrue(true);
        }catch(\Exception $e){
            $this->fail($e->getMessage());
        }
    }

    public function testConstructorThrowsExceptionWhenObjectGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Group parameter must be a string of an array of strings");
        new ProjectGroupModel(new stdClass());
    }

    public function testConstructorThrowsExceptionWhenNotOnlyStringsGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Elements of the Project Group can only be strings, given = ");
        new ProjectGroupModel(['this','is',1, new stdClass(), []]);
    }

    public function testAddGroups(): void
    {
        $group = new ProjectGroupModel('test-group');
        $group = $group->add('another-group');
        $group = $group->add('this group');
        $group = $group->add('that group');
        $this->assertContains('test-group', $group->getData());
        $this->assertContains('another-group', $group->getData());
        $this->assertContains('this group', $group->getData());
        $this->assertContains('that group', $group->getData());
    }

    public function testRemoveGroups(): void
    {
        $group = new ProjectGroupModel(['test-group', 'another-group', 'this', 'that']);
        $group = $group->remove('this');
        $this->assertFalse($group->has('this'));
        $this->assertTrue($group->has('that'));
    }

    public function testConvertFromCsv(): void
    {
        $input = 'a,b,c, d, e, f';
        $group = new ProjectGroupModel($input);
        $output = array_map('trim', explode(',', $input));
        foreach($output as $value) {
            $this->assertTrue($group->has($value));
        }
        $this->assertFalse($group->has(' d'));
        $this->assertFalse($group->has('g'));
    }

    public function testConvertToCsv(): void
    {
        $group = new ProjectGroupModel('test-group');
        $this->assertContains('test-group', $group->getData());
        $group = $group->add('another-group');
        $this->assertContains('another-group', $group->getData());
        $group = $group->remove('test-group');
        $this->assertNotContains('test-group', $group->getData());
        $this->assertContains('another-group', $group->getData());
        $group = $group->add('third-group');
        $this->assertEquals('another-group,third-group', $group->toCsv());
    }
}