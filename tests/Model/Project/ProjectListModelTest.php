<?php declare(strict_types=1);

namespace DDT\Test\Model\Project;

use DDT\Model\Project\ProjectGroupModel;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathModel;
use PHPUnit\Framework\TestCase;

class ProjectListModelTest extends TestCase
{
    private $testDataPath = __DIR__ . '/../../Data';

    private function getPath(?string $project=null)
    {
        return rtrim($this->testDataPath . '/' . $project, '/');
    }
    
    public function testCreateEmptyList(): void
    {
        $list = ProjectListModel::fromArray([]);
        $this->assertInstanceOf(ProjectListModel::class, $list);
    }

    public function testAddByRawArray(): void
    {
        $model = ProjectModel::fromArray([
            'path' => $this->getPath('path-a/service-a'), 
            'name' => 'test-service', 
            'group' => new ProjectGroupModel('test-group')
        ]);
        $list = ProjectListModel::fromArray($l=[$model]);

        var_dump(['l current' => current($l)]);
        var_dump(['list' => $list]);
        var_dump(['list current' => current($list)]);

        $this->assertInstanceOf(ProjectListModel::class, $list);
        $this->assertInstanceOf(ProjectModel::class, current($list));
    }

    public function testAddProjectPathModel(): void
    {
        $path = ProjectPathModel::fromArray(['path' => $this->getPath(), 'group' => new ProjectGroupModel('test-group')]);
        $list = ProjectListModel::fromArray([$path]);

        $this->assertInstanceOf(ProjectListModel::class, $list);
        $this->assertInstanceOf(ProjectModel::class, current($list));
    }
}