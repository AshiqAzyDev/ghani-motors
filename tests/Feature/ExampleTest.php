<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Without a dealer, the public site sends visitors to first-time setup.
     */
    public function test_the_application_redirects_to_setup_when_unconfigured(): void
    {
        Schema::dropIfExists('dealers');
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->get('/')->assertRedirect(route('setup.dealer'));
    }
}
