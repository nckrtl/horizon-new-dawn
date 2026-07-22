import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import pending from './pending'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::index
* @see src/Http/Controllers/JobController.php:18
* @route '/horizon/jobs/{type}'
*/
export const index = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/jobs/{type}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::index
* @see src/Http/Controllers/JobController.php:18
* @route '/horizon/jobs/{type}'
*/
index.url = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { type: args }
    }

    if (Array.isArray(args)) {
        args = {
            type: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        type: args.type,
    }

    return index.definition.url
            .replace('{type}', parsedArgs.type.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::index
* @see src/Http/Controllers/JobController.php:18
* @route '/horizon/jobs/{type}'
*/
index.get = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::index
* @see src/Http/Controllers/JobController.php:18
* @route '/horizon/jobs/{type}'
*/
index.head = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::show
* @see src/Http/Controllers/JobController.php:42
* @route '/horizon/jobs/{type}/{job}'
*/
export const show = (args: { type: string | number, job: string | number } | [type: string | number, job: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/jobs/{type}/{job}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::show
* @see src/Http/Controllers/JobController.php:42
* @route '/horizon/jobs/{type}/{job}'
*/
show.url = (args: { type: string | number, job: string | number } | [type: string | number, job: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            type: args[0],
            job: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        type: args.type,
        job: args.job,
    }

    return show.definition.url
            .replace('{type}', parsedArgs.type.toString())
            .replace('{job}', parsedArgs.job.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::show
* @see src/Http/Controllers/JobController.php:42
* @route '/horizon/jobs/{type}/{job}'
*/
show.get = (args: { type: string | number, job: string | number } | [type: string | number, job: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\JobController::show
* @see src/Http/Controllers/JobController.php:42
* @route '/horizon/jobs/{type}/{job}'
*/
show.head = (args: { type: string | number, job: string | number } | [type: string | number, job: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const jobs = {
    pending: Object.assign(pending, pending),
    index: Object.assign(index, index),
    show: Object.assign(show, show),
}

export default jobs