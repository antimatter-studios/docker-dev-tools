<?php declare(strict_types=1);

namespace DDT\Test\Model\Project;

use DDT\Model\Project\ProjectGroupModel;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathListModel;
use DDT\Model\Project\ProjectPathModel;
use PHPUnit\Framework\TestCase;

class ProjectPathListModelTest extends TestCase
{
    private $testDataPath = __DIR__ . '/../../data';

    private function getPath(?string $path=null)
    {
        return rtrim($this->testDataPath . '/' . $path, '/');
    }
    
    public function testCreateEmptyList(): void
    {
        $list = ProjectPathListModel::fromArray([]);
        $this->assertInstanceOf(ProjectPathListModel::class, $list);
    }

    public function testBasicArrayFunctionality(): void
    {
        $data = [
            ProjectPathModel::fromArray(['path' => $this->getPath('path-a'), 'group' => 'group-a']),
            ProjectPathModel::fromArray(['path' => $this->getPath('path-b'), 'group' => 'group-b']),
        ];

        $list = ProjectPathListModel::fromArray($data);
        $this->assertCount(2, $list);
        $this->assertEquals(realpath($this->getPath('path-a')), $list->key());
        $this->assertInstanceOf(ProjectPathModel::class, $list->first());
        $this->assertTrue($list->first()->hasGroup('group-a'));
        $this->assertTrue($list->next()->hasGroup('group-b'));
    }
}