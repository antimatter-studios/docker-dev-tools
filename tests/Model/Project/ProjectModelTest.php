<?php declare(strict_types=1);

namespace DDT\Test\Model\Project;

use DDT\Model\Project\ProjectGroupModel;
use DDT\Model\Project\ProjectModel;
use PHPUnit\Framework\TestCase;

class ProjectModelTest extends TestCase
{
    private $testDataPath = __DIR__ . '/../../Data';

    private function getPath(?string $project=null)
    {
        return rtrim($this->testDataPath . '/' . $project, '/');
    }
    
    public function testBasicProject(): void
    {
        $model = ProjectModel::fromArray([
            'path' => $path = $this->getPath('path-a/service-a'), 
            'name' => $name = 'test-service', 
            'group' => $groups = new ProjectGroupModel('test-group')
        ]);

        $this->assertInstanceOf(ProjectModel::class, $model);
        $this->assertInstanceOf(ProjectGroupModel::class, $model->getGroups());
        $this->assertEquals($model->getname(), $name);
        $this->assertEquals($model->getPath(), realpath($path));
    }
}