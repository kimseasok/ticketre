<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

afterEach(function () {
    \Mockery::close();
});
