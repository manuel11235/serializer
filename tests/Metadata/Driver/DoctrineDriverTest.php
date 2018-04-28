<?php

declare(strict_types=1);

/*
 * Copyright 2016 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer\Tests\Metadata\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver as DoctrineDriver;
use JMS\Serializer\Metadata\Driver\AnnotationDriver;
use JMS\Serializer\Metadata\Driver\DoctrineTypeDriver;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;

class DoctrineDriverTest extends \PHPUnit\Framework\TestCase
{
    public function getMetadata()
    {
        $refClass = new \ReflectionClass('JMS\Serializer\Tests\Fixtures\Doctrine\BlogPost');
        $metadata = $this->getDoctrineDriver()->loadMetadataForClass($refClass);

        return $metadata;
    }

    public function testTypelessPropertyIsGivenTypeFromDoctrineMetadata()
    {
        $metadata = $this->getMetadata();

        $this->assertEquals(
            ['name' => 'DateTime', 'params' => []],
            $metadata->propertyMetadata['createdAt']->type
        );
    }

    public function testSingleValuedAssociationIsProperlyHinted()
    {
        $metadata = $this->getMetadata();
        $this->assertEquals(
            ['name' => 'JMS\Serializer\Tests\Fixtures\Doctrine\Author', 'params' => []],
            $metadata->propertyMetadata['author']->type
        );
    }

    public function testMultiValuedAssociationIsProperlyHinted()
    {
        $metadata = $this->getMetadata();

        $this->assertEquals(
            ['name' => 'ArrayCollection', 'params' => [
                ['name' => 'JMS\Serializer\Tests\Fixtures\Doctrine\Comment', 'params' => []]]
            ],
            $metadata->propertyMetadata['comments']->type
        );
    }

    public function testTypeGuessByDoctrineIsOverwrittenByDelegateDriver()
    {
        $metadata = $this->getMetadata();

        // This would be guessed as boolean but we've overriden it to integer
        $this->assertEquals(
            ['name' => 'integer', 'params' => []],
            $metadata->propertyMetadata['published']->type
        );
    }

    public function testUnknownDoctrineTypeDoesNotResultInAGuess()
    {
        $metadata = $this->getMetadata();
        $this->assertNull($metadata->propertyMetadata['slug']->type);
    }

    public function testNonDoctrineEntityClassIsNotModified()
    {
        // Note: Using regular BlogPost fixture here instead of Doctrine fixture
        // because it has no Doctrine metadata.
        $refClass = new \ReflectionClass('JMS\Serializer\Tests\Fixtures\BlogPost');

        $plainMetadata = $this->getAnnotationDriver()->loadMetadataForClass($refClass);
        $doctrineMetadata = $this->getDoctrineDriver()->loadMetadataForClass($refClass);

        // Do not compare timestamps
        if (abs($doctrineMetadata->createdAt - $plainMetadata->createdAt) < 2) {
            $plainMetadata->createdAt = $doctrineMetadata->createdAt;
        }

        $this->assertEquals($plainMetadata, $doctrineMetadata);
    }

    public function testExcludePropertyNoPublicAccessorException()
    {
        $first = $this->getAnnotationDriver()
            ->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\ExcludePublicAccessor'));

        $this->assertArrayHasKey('id', $first->propertyMetadata);
        $this->assertArrayNotHasKey('iShallNotBeAccessed', $first->propertyMetadata);
    }

    public function testVirtualPropertiesAreNotModified()
    {
        $doctrineMetadata = $this->getMetadata();
        $this->assertNull($doctrineMetadata->propertyMetadata['ref']->type);
    }

    public function testGuidPropertyIsGivenStringType()
    {
        $metadata = $this->getMetadata();

        $this->assertEquals(
            ['name' => 'string', 'params' => []],
            $metadata->propertyMetadata['id']->type
        );
    }

    protected function getEntityManager()
    {
        $config = new Configuration();
        $config->setProxyDir(sys_get_temp_dir() . '/JMSDoctrineTestProxies');
        $config->setProxyNamespace('JMS\Tests\Proxies');
        $config->setMetadataDriverImpl(
            new DoctrineDriver(new AnnotationReader(), __DIR__ . '/../../Fixtures/Doctrine')
        );

        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        return EntityManager::create($conn, $config);
    }

    public function getAnnotationDriver()
    {
        return new AnnotationDriver(new AnnotationReader(), new IdenticalPropertyNamingStrategy());
    }

    protected function getDoctrineDriver()
    {
        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $registry->expects($this->atLeastOnce())
            ->method('getManagerForClass')
            ->will($this->returnValue($this->getEntityManager()));

        return new DoctrineTypeDriver(
            $this->getAnnotationDriver(),
            $registry
        );
    }
}
