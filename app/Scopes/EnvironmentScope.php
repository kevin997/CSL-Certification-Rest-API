<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class EnvironmentScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Only apply if the table has an environment_id column and there's a current environment in the session
        if (Schema::hasColumn($model->getTable(), 'environment_id') && session()->has('current_environment_id')) {
            $builder->where(function ($query) use ($model) {
                $query->where($model->getTable() . '.environment_id', session('current_environment_id'))
                      ->orWhereNull($model->getTable() . '.environment_id');
            });
        }
    }
}
