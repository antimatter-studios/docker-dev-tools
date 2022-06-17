<?php declare(strict_types=1);

namespace DDT\Test\Config;

use DDT\Config\Sections\ProjectConfig;
use DDT\Config\SystemConfig;
use DDT\Model\Project\ProjectModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectConfigTest extends TestCase
{
    private $testDataPath = __DIR__ . '/../data';

    private function getPath(?string $project=null)
    {
        return rtrim($this->testDataPath . '/' . $project, '/');
    }

    public function testOnlyPathsReturn(): void
    {
        /** @var MockObject|SystemConfig */
        $systemConfig = $this->getMockBuilder(SystemConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getVersion', 'getKey'])
            ->getMock();
        
        $systemConfig
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn(3);

        $expectedResult = [
            $this->getPath('path-a') => [
                "path" => $this->getPath('path-a'),
                "group" => [
                    "path-group-a"
                ]
            ]
        ];

        $systemConfig
            ->expects($this->once())
            ->method('getKey')
            ->willReturn($expectedResult);

        $projectConfig = new ProjectConfig($systemConfig);
        $list = $projectConfig->listProjects(ProjectConfig::LIST_PATHS);

        $this->assertCount(3, $list);

        $compareData = [
            $this->getPath('path-a/service-a'),
            $this->getPath('path-a/service-b'),
            $this->getPath('path-a/service-c'),
        ];

        foreach($compareData as $path){
            $this->assertInstanceOf(ProjectModel::class, $list->findProjectByPath($path));
        }
    }

    public function testOnlyProjectsReturn(): void
    {
        /** @var MockObject|SystemConfig */
        $systemConfig = $this->getMockBuilder(SystemConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getVersion', 'getKey'])
            ->getMock();
        
        $systemConfig
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn(3);

        $expectedResult = [
            $this->getPath('path-a/service-a') => [
                "path" => $this->getPath('path-a/service-a'),
                "name" => basename($this->getPath('path-a/service-a')),
                "group" => [
                    "path-group-a"
                ]
            ],
            $this->getPath('path-b/service-e') => [
                "path" => $this->getPath('path-b/service-e'),
                "name" => basename($this->getPath('path-b/service-e')),
                "group" => [
                    "path-group-a"
                ]
            ]
        ];

        $systemConfig
            ->expects($this->once())
            ->method('getKey')
            ->willReturn($expectedResult);

        $projectConfig = new ProjectConfig($systemConfig);
        $list = $projectConfig->listProjects(ProjectConfig::LIST_PROJECTS);

        $this->assertCount(2, $list);

        $compareData = [
            $this->getPath('path-a/service-a'),
            $this->getPath('path-b/service-e'),
        ];

        foreach($compareData as $path){
            $this->assertInstanceOf(ProjectModel::class, $list->findProjectByPath($path));
        }
    }
}