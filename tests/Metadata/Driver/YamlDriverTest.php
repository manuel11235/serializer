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

use JMS\Serializer\Metadata\Driver\YamlDriver;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use Metadata\Driver\FileLocator;

class YamlDriverTest extends BaseDriverTest
{
    public function testAccessorOrderIsInferred()
    {
        $m = $this->getDriverForSubDir('accessor_inferred')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\Person'));
        $this->assertEquals(['age', 'name'], array_keys($m->propertyMetadata));
    }

    public function testShortExposeSyntax()
    {
        $m = $this->getDriverForSubDir('short_expose')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\Person'));

        $this->assertArrayHasKey('name', $m->propertyMetadata);
        $this->assertArrayNotHasKey('age', $m->propertyMetadata);
    }

    public function testBlogPost()
    {
        $m = $this->getDriverForSubDir('exclude_all')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\BlogPost'));

        $this->assertArrayHasKey('title', $m->propertyMetadata);

        $excluded = ['createdAt', 'published', 'comments', 'author'];
        foreach ($excluded as $key) {
            $this->assertArrayNotHasKey($key, $m->propertyMetadata);
        }
    }

    public function testBlogPostExcludeNoneStrategy()
    {
        $m = $this->getDriverForSubDir('exclude_none')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\BlogPost'));

        $this->assertArrayNotHasKey('title', $m->propertyMetadata);

        $excluded = ['createdAt', 'published', 'comments', 'author'];
        foreach ($excluded as $key) {
            $this->assertArrayHasKey($key, $m->propertyMetadata);
        }
    }

    public function testBlogPostCaseInsensitive()
    {
        $m = $this->getDriverForSubDir('case')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\BlogPost'));

        $p = new PropertyMetadata($m->name, 'title');
        $p->serializedName = 'title';
        $p->type = ['name' => 'string', 'params' => []];
        $this->assertEquals($p, $m->propertyMetadata['title']);
    }

    public function testBlogPostAccessor()
    {
        $m = $this->getDriverForSubDir('accessor')->loadMetadataForClass(new \ReflectionClass('JMS\Serializer\Tests\Fixtures\BlogPost'));

        $this->assertArrayHasKey('title', $m->propertyMetadata);

        $p = new PropertyMetadata($m->name, 'title');
        $p->getter = 'getOtherTitle';
        $p->setter = 'setOtherTitle';
        $p->serializedName = 'title';
        $this->assertEquals($p, $m->propertyMetadata['title']);
    }

    private function getDriverForSubDir($subDir = null)
    {
        return new YamlDriver(new FileLocator([
            'JMS\Serializer\Tests\Fixtures' => __DIR__ . '/yml' . ($subDir ? '/' . $subDir : ''),
        ]), new IdenticalPropertyNamingStrategy());
    }

    protected function getDriver()
    {
        return $this->getDriverForSubDir();
    }
}
