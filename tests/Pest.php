<?php

use Mockery;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

afterEach(function () {
    Mockery::close();
});
