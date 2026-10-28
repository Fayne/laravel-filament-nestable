<?php

use Fayne\FilamentNestable\NestableServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

it('provides matching interface catalogs and the hyphenated Chinese alias', function () {
    $path = dirname(__DIR__, 2).'/resources/lang';
    foreach (glob($path.'/en/*.php') as $file) {
        $name = basename($file);
        $english = require $file;
        $chinese = require $path.'/zh_CN/'.$name;
        $alias = require $path.'/zh-CN/'.$name;
        expect(array_keys($chinese))->toBe(array_keys($english))
            ->and($alias)->toBe($chinese);
        foreach ($english as $key => $value) {
            expect($value)->toBeString()->not->toBe('');
            preg_match_all('/:([a-z_]+)/', $value, $englishParameters);
            preg_match_all('/:([a-z_]+)/', $chinese[$key], $chineseParameters);
            $englishParameters[0] = array_values(array_unique($englishParameters[0]));
            sort($englishParameters[0]);
            $chineseParameters[0] = array_values(array_unique($chineseParameters[0]));
            sort($chineseParameters[0]);
            expect($chineseParameters[0])->toBe($englishParameters[0]);
        }
    }
});

it('loads interface translations during provider registration without changing content language', function () {
    $loader = new FileLoader(app('files'), sys_get_temp_dir().'/unregistered-package-translations');
    app()->instance('translator', new Translator($loader, 'en'));
    $provider = new NestableServiceProvider(app());
    $provider->register();
    $default = config('filament-cms.default_locale');
    foreach (['en' => 'Save', 'zh_CN' => '保存', 'zh-CN' => '保存'] as $locale => $expected) {
        app()->setLocale($locale);
        expect(__('filament-nestable::ui.save_tree'))->toBe($expected)
            ->and(config('filament-cms.default_locale'))->toBe($default);
    }
});
