import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueBatchRetryController::store
* @see src/Http/Controllers/QueueBatchRetryController.php:14
* @route '/horizon/queues/{queue}/batches/retry-failed-jobs'
*/
export const store = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/queues/{queue}/batches/retry-failed-jobs',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueBatchRetryController::store
* @see src/Http/Controllers/QueueBatchRetryController.php:14
* @route '/horizon/queues/{queue}/batches/retry-failed-jobs'
*/
store.url = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{queue}', parsedArgs.queue.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueBatchRetryController::store
* @see src/Http/Controllers/QueueBatchRetryController.php:14
* @route '/horizon/queues/{queue}/batches/retry-failed-jobs'
*/
store.post = (args: { queue: string | number } | [queue: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const retryFailed = {
    store: Object.assign(store, store),
}

export default retryFailed