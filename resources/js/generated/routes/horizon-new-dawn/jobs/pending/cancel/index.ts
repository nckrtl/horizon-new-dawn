import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobsCancellationController::destroy
* @see src/Http/Controllers/PendingJobsCancellationController.php:16
* @route '/horizon/jobs/pending/cancel/{scope}'
*/
export const destroy = (args: { scope: string | number } | [scope: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/jobs/pending/cancel/{scope}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobsCancellationController::destroy
* @see src/Http/Controllers/PendingJobsCancellationController.php:16
* @route '/horizon/jobs/pending/cancel/{scope}'
*/
destroy.url = (args: { scope: string | number } | [scope: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { scope: args }
    }

    if (Array.isArray(args)) {
        args = {
            scope: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        scope: args.scope,
    }

    return destroy.definition.url
            .replace('{scope}', parsedArgs.scope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobsCancellationController::destroy
* @see src/Http/Controllers/PendingJobsCancellationController.php:16
* @route '/horizon/jobs/pending/cancel/{scope}'
*/
destroy.delete = (args: { scope: string | number } | [scope: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const cancel = {
    destroy: Object.assign(destroy, destroy),
}

export default cancel