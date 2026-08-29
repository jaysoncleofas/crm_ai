<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\AiConversation;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Stable, short polymorphic keys: the audit trail and taggables store
        // "contact" rather than "App\Models\Contact", so class moves are safe.
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'contact' => Contact::class,
            'deal' => Deal::class,
            'activity' => Activity::class,
            'pipeline' => Pipeline::class,
            'pipeline_stage' => PipelineStage::class,
            'tag' => Tag::class,
            'ai_conversation' => AiConversation::class,
        ]);

        // Surface N+1 queries and typo'd attributes during development instead
        // of letting them reach production.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
