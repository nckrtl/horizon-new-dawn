import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import clearAll from './clear-all'
import retryAll from './retry-all'
import retry from './retry'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::index
* @see src/Http/Controllers/FailedJobController.php:20
* @route '/horizon/failed'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/failed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::index
* @see src/Http/Controllers/FailedJobController.php:20
* @route '/horizon/failed'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::index
* @see src/Http/Controllers/FailedJobController.php:20
* @route '/horizon/failed'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::index
* @see src/Http/Controllers/FailedJobController.php:20
* @route '/horizon/failed'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::show
* @see src/Http/Controllers/FailedJobController.php:44
* @route '/horizon/failed/{job}'
*/
export const show = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/failed/{job}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::show
* @see src/Http/Controllers/FailedJobController.php:44
* @route '/horizon/failed/{job}'
*/
show.url = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { job: args }
    }

    if (Array.isArray(args)) {
        args = {
            job: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        job: args.job,
    }

    return show.definition.url
            .replace('{job}', parsedArgs.job.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::show
* @see src/Http/Controllers/FailedJobController.php:44
* @route '/horizon/failed/{job}'
*/
show.get = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::show
* @see src/Http/Controllers/FailedJobController.php:44
* @route '/horizon/failed/{job}'
*/
show.head = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::destroy
* @see src/Http/Controllers/FailedJobController.php:56
* @route '/horizon/failed/{job}'
*/
export const destroy = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/failed/{job}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::destroy
* @see src/Http/Controllers/FailedJobController.php:56
* @route '/horizon/failed/{job}'
*/
destroy.url = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { job: args }
    }

    if (Array.isArray(args)) {
        args = {
            job: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        job: args.job,
    }

    return destroy.definition.url
            .replace('{job}', parsedArgs.job.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobController::destroy
* @see src/Http/Controllers/FailedJobController.php:56
* @route '/horizon/failed/{job}'
*/
destroy.delete = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const failedJobs = {
    index: Object.assign(index, index),
    clearAll: Object.assign(clearAll, clearAll),
    retryAll: Object.assign(retryAll, retryAll),
    show: Object.assign(show, show),
    destroy: Object.assign(destroy, destroy),
    retry: Object.assign(retry, retry),
}

export default failedJobs