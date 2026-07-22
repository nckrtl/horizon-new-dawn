import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearController::destroy
* @see src/Http/Controllers/QueueClearController.php:15
* @route '/horizon/queues/{connection}/{queue}/clear'
*/
export const destroy = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/queues/{connection}/{queue}/clear',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearController::destroy
* @see src/Http/Controllers/QueueClearController.php:15
* @route '/horizon/queues/{connection}/{queue}/clear'
*/
destroy.url = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{connection}', parsedArgs.connection.toString())
            .replace('{queue}', parsedArgs.queue.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearController::destroy
* @see src/Http/Controllers/QueueClearController.php:15
* @route '/horizon/queues/{connection}/{queue}/clear'
*/
destroy.delete = (args: { connection: string | number, queue: string | number } | [connection: string | number, queue: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const clear = {
    destroy: Object.assign(destroy, destroy),
}

export default clear