import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueFailedJobRetryController::store
* @see src/Http/Controllers/QueueFailedJobRetryController.php:15
* @route '/horizon/queues/{connection}/{queue}/retry-failed'
*/
export const store = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/queues/{connection}/{queue}/retry-failed',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueFailedJobRetryController::store
* @see src/Http/Controllers/QueueFailedJobRetryController.php:15
* @route '/horizon/queues/{connection}/{queue}/retry-failed'
*/
store.url = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            connection: args[0],
            queue: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        connection: args.connection,
        queue: args.queue,
    }

    return store.definition.url
            .replace('{connection}', parsedArgs.connection.toString())
            .replace('{queue}', parsedArgs.queue.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueFailedJobRetryController::store
* @see src/Http/Controllers/QueueFailedJobRetryController.php:15
* @route '/horizon/queues/{connection}/{queue}/retry-failed'
*/
store.post = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const retryFailed = {
    store: Object.assign(store, store),
}

export default retryFailed