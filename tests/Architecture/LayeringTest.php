<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LayeringTest
{
    public function testDomainIsFrameworkAgnostic(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Payroll\Domain'), Selector::inNamespace('App\Shared\Domain'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Illuminate'),
                Selector::inNamespace('App\Payroll\Application'),
                Selector::inNamespace('App\Payroll\Infrastructure'),
                Selector::inNamespace('App\Payroll\UserInterface'),
                Selector::inNamespace('App\Shared\Application'),
                Selector::inNamespace('App\Shared\Infrastructure'),
            )
            ->because('the domain model must not depend on the framework or on outer layers');
    }

    public function testApplicationDependsOnlyOnDomainAndPorts(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Payroll\Application'), Selector::inNamespace('App\Shared\Application'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Illuminate'),
                Selector::inNamespace('App\Payroll\Infrastructure'),
                Selector::inNamespace('App\Payroll\UserInterface'),
                Selector::inNamespace('App\Shared\Infrastructure'),
            )
            ->because('use cases talk to the outside world only through ports');
    }

    public function testDomainEventsAreImmutable(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::implements('App\Shared\Domain\EventSourcing\DomainEvent'))
            ->shouldBeReadonly()
            ->because('an event is a fact that already happened and can never change');
    }
}
