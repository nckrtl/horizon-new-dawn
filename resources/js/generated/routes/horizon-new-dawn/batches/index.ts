import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import clearFinished from './clear-finished'
import cancel from './cancel'
import retry from './retry'
import failed from './failed'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::index
* @see src/Http/Controllers/BatchController.php:17
* @route '/horizon/batches'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/batches',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::index
* @see src/Http/Controllers/BatchController.php:17
* @route '/horizon/batches'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::index
* @see src/Http/Controllers/BatchController.php:17
* @route '/horizon/batches'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::index
* @see src/Http/Controllers/BatchController.php:17
* @route '/horizon/batches'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::show
* @see src/Http/Controllers/BatchController.php:49
* @route '/horizon/batches/{batch}'
*/
export const show = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/batches/{batch}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::show
* @see src/Http/Controllers/BatchController.php:49
* @route '/horizon/batches/{batch}'
*/
show.url = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { batch: args }
    }

    if (Array.isArray(args)) {
        args = {
            batch: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        batch: args.batch,
    }

    return show.definition.url
            .replace('{batch}', parsedArgs.batch.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::show
* @see src/Http/Controllers/BatchController.php:49
* @route '/horizon/batches/{batch}'
*/
show.get = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchController::show
* @see src/Http/Controllers/BatchController.php:49
* @route '/horizon/batches/{batch}'
*/
show.head = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const batches = {
    index: Object.assign(index, index),
    clearFinished: Object.assign(clearFinished, clearFinished),
    show: Object.assign(show, show),
    cancel: Object.assign(cancel, cancel),
    retry: Object.assign(retry, retry),
    failed: Object.assign(failed, failed),
}

export default batches