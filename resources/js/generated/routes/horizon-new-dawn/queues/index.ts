import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import clearAll from './clear-all'
import pause from './pause'
import clear from './clear'
import retryFailed from './retry-failed'
import batches from './batches'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::index
* @see src/Http/Controllers/QueueController.php:24
* @route '/horizon/queues'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/queues',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::index
* @see src/Http/Controllers/QueueController.php:24
* @route '/horizon/queues'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::index
* @see src/Http/Controllers/QueueController.php:24
* @route '/horizon/queues'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::index
* @see src/Http/Controllers/QueueController.php:24
* @route '/horizon/queues'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::show
* @see src/Http/Controllers/QueueController.php:32
* @route '/horizon/queues/{queue}'
*/
export const show = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/queues/{queue}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::show
* @see src/Http/Controllers/QueueController.php:32
* @route '/horizon/queues/{queue}'
*/
show.url = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { queue: args }
    }

    if (Array.isArray(args)) {
        args = {
            queue: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        queue: args.queue,
    }

    return show.definition.url
            .replace('{queue}', parsedArgs.queue.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::show
* @see src/Http/Controllers/QueueController.php:32
* @route '/horizon/queues/{queue}'
*/
show.get = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueController::show
* @see src/Http/Controllers/QueueController.php:32
* @route '/horizon/queues/{queue}'
*/
show.head = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const queues = {
    index: Object.assign(index, index),
    clearAll: Object.assign(clearAll, clearAll),
    show: Object.assign(show, show),
    pause: Object.assign(pause, pause),
    clear: Object.assign(clear, clear),
    retryFailed: Object.assign(retryFailed, retryFailed),
    batches: Object.assign(batches, batches),
}

export default queues