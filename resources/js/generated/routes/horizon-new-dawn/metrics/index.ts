import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::redirect
* @see src/Http/Controllers/MetricsController.php:17
* @route '/horizon/metrics'
*/
export const redirect = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
})

redirect.definition = {
    methods: ["get","head"],
    url: '/horizon/metrics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::redirect
* @see src/Http/Controllers/MetricsController.php:17
* @route '/horizon/metrics'
*/
redirect.url = (options?: RouteQueryOptions) => {
    return redirect.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::redirect
* @see src/Http/Controllers/MetricsController.php:17
* @route '/horizon/metrics'
*/
redirect.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::redirect
* @see src/Http/Controllers/MetricsController.php:17
* @route '/horizon/metrics'
*/
redirect.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: redirect.url(options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::index
* @see src/Http/Controllers/MetricsController.php:24
* @route '/horizon/metrics/{type}'
*/
export const index = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/metrics/{type}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::index
* @see src/Http/Controllers/MetricsController.php:24
* @route '/horizon/metrics/{type}'
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
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::index
* @see src/Http/Controllers/MetricsController.php:24
* @route '/horizon/metrics/{type}'
*/
index.get = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricsController::index
* @see src/Http/Controllers/MetricsController.php:24
* @route '/horizon/metrics/{type}'
*/
index.head = (args: { type: string | number } | [type: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricController::show
* @see src/Http/Controllers/MetricController.php:16
* @route '/horizon/metrics/{type}/{slug}'
*/
export const show = (args: { type: string | number, slug: string | number } | [type: string | number, slug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/metrics/{type}/{slug}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricController::show
* @see src/Http/Controllers/MetricController.php:16
* @route '/horizon/metrics/{type}/{slug}'
*/
show.url = (args: { type: string | number, slug: string | number } | [type: string | number, slug: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            type: args[0],
            slug: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        type: args.type,
        slug: args.slug,
    }

    return show.definition.url
            .replace('{type}', parsedArgs.type.toString())
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricController::show
* @see src/Http/Controllers/MetricController.php:16
* @route '/horizon/metrics/{type}/{slug}'
*/
show.get = (args: { type: string | number, slug: string | number } | [type: string | number, slug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MetricController::show
* @see src/Http/Controllers/MetricController.php:16
* @route '/horizon/metrics/{type}/{slug}'
*/
show.head = (args: { type: string | number, slug: string | number } | [type: string | number, slug: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const metrics = {
    redirect: Object.assign(redirect, redirect),
    index: Object.assign(index, index),
    show: Object.assign(show, show),
}

export default metrics