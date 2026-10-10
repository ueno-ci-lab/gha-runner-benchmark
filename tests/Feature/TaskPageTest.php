<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaskPageTest extends TestCase
{
    public function test_task_page_is_displayed(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('タスク管理（動作確認）')
            ->assertSee("const API = '/api/tasks';", false);
    }
}
