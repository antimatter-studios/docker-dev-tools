<?php declare(strict_types=1);

namespace DDT\Test\Model\Project;

use DDT\Model\Project\ProjectGroupModel;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathModel;
use PHPUnit\Framework\TestCase;

class ProjectListModelTest extends TestCase
{
    private $testDataPath = __DIR__ . '/../../data';

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
        $model = ProjectModel::fromArray(['path' => __DIR__, 'name' => 'test-service', 'group' => new ProjectGroupModel('test-group')]);
        $list = ProjectListModel::fromArray([$model]);

        $this->assertInstanceOf(ProjectListModel::class, $list);
    }

    public function testAddProjectPathModel(): void
    {
        $path = ProjectPathModel::fromArray(['path' => $this->getPath(), 'group' => new ProjectGroupModel('test-group')]);
        $list = ProjectListModel::fromArray($path);

        $this->assertInstanceOf(ProjectListModel::class, $list);
    }
}