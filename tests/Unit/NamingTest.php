<?php

namespace Nacer\JdlToFilament\Tests\Unit;

use Nacer\JdlToFilament\Naming;
use PHPUnit\Framework\TestCase;

class NamingTest extends TestCase
{
    public function test_pivot_name_is_direction_independent(): void
    {
        self::assertSame('course_student', Naming::pivotTableName('Student', 'Course'));
        self::assertSame('course_student', Naming::pivotTableName('Course', 'Student'));
    }

    public function test_multiword_names_are_normalized(): void
    {
        self::assertSame('order_item_product', Naming::pivotTableName('OrderItem', 'Product'));
        self::assertSame('order_item_id', Naming::pivotForeignKey('OrderItem'));
    }
}
