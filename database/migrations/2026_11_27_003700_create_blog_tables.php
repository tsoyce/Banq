<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A37 · Блог и новости: рубрики, публикации, теги, связи с банками и продуктами,
 * реакции и комментарии читателей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->string('color', 7)->nullable();                     // #rrggbb — метка рубрики
            $table->string('icon', 30)->nullable();                     // имя из partials.icon
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();      // null — для всех стран
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20)->default('article');             // Post::KINDS: article | news | review | guide | analytics
            $table->string('status', 20)->default('draft');             // draft | published (published_at в будущем = запланирован)
            $table->timestamp('published_at')->nullable();
            $table->string('title', 200);
            $table->string('slug', 191)->unique();
            $table->string('lead', 400)->nullable();                    // подзаголовок / анонс
            $table->longText('body')->nullable();                       // очищенный HTML (App\Support\Blog\HtmlSanitizer)
            $table->string('cover')->nullable();                        // путь в public или https://
            $table->string('cover_alt', 200)->nullable();
            $table->string('cover_caption', 200)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->boolean('is_featured')->default(false);             // главный материал ленты
            $table->boolean('allow_comments')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('source_name', 120)->nullable();             // для новостей: первоисточник
            $table->string('source_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        // Связи публикации с сущностями портала: bank | deposit | card | package | payment_system | card_tier (morph map).
        // is_primary = отмечено редактором; false = выведено каскадом (карта → банк, пакеты, платёжная система, уровень)
        Schema::create('postables', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('postable_type', 20);
            $table->unsignedBigInteger('postable_id');
            $table->boolean('is_primary')->default(true);
            $table->primary(['post_id', 'postable_type', 'postable_id']);
            $table->index(['postable_type', 'postable_id']);
        });

        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                                 // Post::REACTIONS: like | fire | think | sad
            $table->timestamps();
            $table->unique(['post_id', 'user_id', 'kind']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 12)->default('approved');           // approved | pending | hidden
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status']);
        });

        // Стартовые рубрики — редактор правит их в админке
        $now = now();
        DB::table('post_categories')->insert(array_map(fn ($c) => $c + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['slug' => 'news', 'name' => 'Новости', 'description' => 'Ставки, решения регулятора, запуски продуктов', 'color' => '#7b5cff', 'icon' => 'news', 'sort' => 10],
            ['slug' => 'analytics', 'name' => 'Аналитика', 'description' => 'Цифры рынка и движение ставок', 'color' => '#22d3ee', 'icon' => 'chart', 'sort' => 20],
            ['slug' => 'reviews', 'name' => 'Обзоры', 'description' => 'Разбираем продукты банков', 'color' => '#f97316', 'icon' => 'star', 'sort' => 30],
            ['slug' => 'guides', 'name' => 'Гайды', 'description' => 'Как выбрать, открыть и не переплатить', 'color' => '#10b981', 'icon' => 'info', 'sort' => 40],
            ['slug' => 'opinion', 'name' => 'Мнения', 'description' => 'Колонки автора', 'color' => '#ec4899', 'icon' => 'quote', 'sort' => 50],
        ]));
    }

    public function down(): void
    {
        foreach (['comments', 'post_reactions', 'postables', 'post_tag', 'tags', 'posts', 'post_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
