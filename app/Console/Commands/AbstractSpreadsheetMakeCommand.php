<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Basis generator untuk kelas Export/Import spreadsheet. Menangani penebakan
 * model (dari opsi --model atau nama kelas) serta pengisian placeholder stub.
 */
abstract class AbstractSpreadsheetMakeCommand extends GeneratorCommand
{
    /**
     * Sufiks nama kelas yang dibuang saat menebak model, mis. "Export".
     */
    abstract protected function classSuffix(): string;

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $model = $this->resolveModel($name);

        return str_replace(
            ['{{ model }}', '{{ modelVariable }}', '{{ filename }}'],
            [$model, Str::camel($model), Str::kebab(Str::plural($model))],
            $stub,
        );
    }

    protected function resolveModel(string $name): string
    {
        $option = $this->option('model');

        if (is_string($option) && $option !== '') {
            return Str::studly(class_basename($option));
        }

        $model = Str::of(class_basename($name))
            ->beforeLast($this->classSuffix())
            ->singular()
            ->studly()
            ->toString();

        return $model !== '' ? $model : 'Model';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Model Eloquent sumber/target data'],
        ];
    }
}
