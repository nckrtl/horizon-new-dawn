import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
import jobs from './jobs'
import retryFailed from './retry-failed'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::index
* @see src/Http/Controllers/MonitoringController.php:20
* @route '/horizon/monitoring'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/monitoring',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::index
* @see src/Http/Controllers/MonitoringController.php:20
* @route '/horizon/monitoring'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::index
* @see src/Http/Controllers/MonitoringController.php:20
* @route '/horizon/monitoring'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::index
* @see src/Http/Controllers/MonitoringController.php:20
* @route '/horizon/monitoring'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::store
* @see src/Http/Controllers/MonitoringController.php:34
* @route '/horizon/monitoring'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/monitoring',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::store
* @see src/Http/Controllers/MonitoringController.php:34
* @route '/horizon/monitoring'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::store
* @see src/Http/Controllers/MonitoringController.php:34
* @route '/horizon/monitoring'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::destroy
* @see src/Http/Controllers/MonitoringController.php:49
* @route '/horizon/monitoring/actions/stop/{tag}'
*/
export const destroy = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/monitoring/actions/stop/{tag}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::destroy
* @see src/Http/Controllers/MonitoringController.php:49
* @route '/horizon/monitoring/actions/stop/{tag}'
*/
destroy.url = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

    if (Array.isArray(args)) {
        args = {
            tag: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tag: args.tag,
    }

    return destroy.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringController::destroy
* @see src/Http/Controllers/MonitoringController.php:49
* @route '/horizon/monitoring/actions/stop/{tag}'
*/
destroy.delete = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringTagController::show
* @see src/Http/Controllers/MonitoringTagController.php:18
* @route '/horizon/monitoring/{tag}/{status?}'
*/
export const show = (args: { tag: string | number, status?: string | number } | [tag: string | number, status: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/monitoring/{tag}/{status?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringTagController::show
* @see src/Http/Controllers/MonitoringTagController.php:18
* @route '/horizon/monitoring/{tag}/{status?}'
*/
show.url = (args: { tag: string | number, status?: string | number } | [tag: string | number, status: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tag: args[0],
            status: args[1],
        }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
        "status",
    ])

    const parsedArgs = {
        tag: args.tag,
        status: args.status,
    }

    return show.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace('{status?}', parsedArgs.status?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringTagController::show
* @see src/Http/Controllers/MonitoringTagController.php:18
* @route '/horizon/monitoring/{tag}/{status?}'
*/
show.get = (args: { tag: string | number, status?: string | number } | [tag: string | number, status: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringTagController::show
* @see src/Http/Controllers/MonitoringTagController.php:18
* @route '/horizon/monitoring/{tag}/{status?}'
*/
show.head = (args: { tag: string | number, status?: string | number } | [tag: string | number, status: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const monitoring = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    jobs: Object.assign(jobs, jobs),
    retryFailed: Object.assign(retryFailed, retryFailed),
    destroy: Object.assign(destroy, destroy),
    show: Object.assign(show, show),
}

export default monitoring