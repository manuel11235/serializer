<?php

declare(strict_types=1);

/*
 * Copyright 2016 Asmir Mustafic <goetas@gmail.com>
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

namespace JMS\Serializer\Tests\Serializer\EventDispatcher\Subscriber;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\Subscriber\SymfonyValidatorSubscriber;
use JMS\Serializer\EventDispatcher\Subscriber\SymfonyValidatorValidatorSubscriber;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class SymfonyValidatorValidatorSubscriberTest extends \PHPUnit\Framework\TestCase
{
    private $validator;

    /** @var SymfonyValidatorSubscriber */
    private $subscriber;

    public function testValidate()
    {
        $obj = new \stdClass;

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($obj, null, ['foo'])
            ->will($this->returnValue(new ConstraintViolationList()));

        $context = DeserializationContext::create()->setAttribute('validation_groups', ['foo']);

        $this->subscriber->onPostDeserialize(new ObjectEvent($context, $obj, []));
    }

    /**
     * @expectedException \JMS\Serializer\Exception\ValidationFailedException
     * @expectedExceptionMessage Validation failed with 1 error(s).
     */
    public function testValidateThrowsExceptionWhenListIsNotEmpty()
    {
        $obj = new \stdClass;

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($obj, null, ['foo'])
            ->will($this->returnValue(new ConstraintViolationList([new ConstraintViolation('foo', 'foo', [], 'a', 'b', 'c')])));

        $context = DeserializationContext::create()->setAttribute('validation_groups', ['foo']);

        $this->subscriber->onPostDeserialize(new ObjectEvent($context, $obj, []));
    }

    public function testValidatorIsNotCalledWhenNoGroupsAreSet()
    {
        $this->validator->expects($this->never())
            ->method('validate');

        $this->subscriber->onPostDeserialize(new ObjectEvent(DeserializationContext::create(), new \stdClass, []));
    }

    public function testValidationIsOnlyPerformedOnRootObject()
    {
        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf('JMS\Serializer\Tests\Fixtures\AuthorList'), null, ['Foo'])
            ->will($this->returnValue(new ConstraintViolationList()));

        $subscriber = $this->subscriber;
        $list = SerializerBuilder::create()
            ->configureListeners(function (EventDispatcher $dispatcher) use ($subscriber) {
                $dispatcher->addSubscriber($subscriber);
            })
            ->build()
            ->deserialize(
                '{"authors":[{"full_name":"foo"},{"full_name":"bar"}]}',
                'JMS\Serializer\Tests\Fixtures\AuthorList',
                'json',
                DeserializationContext::create()->setAttribute('validation_groups', ['Foo'])
            );

        $this->assertCount(2, $list);
    }

    protected function setUp()
    {
        $this->validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();
        $this->subscriber = new SymfonyValidatorValidatorSubscriber($this->validator);
    }
}
