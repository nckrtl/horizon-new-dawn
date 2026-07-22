import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchFailedJobClearController::destroy
* @see src/Http/Controllers/BatchFailedJobClearController.php:13
* @route '/horizon/batches/{batch}/failed'
*/
export const destroy = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/batches/{batch}/failed',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchFailedJobClearController::destroy
* @see src/Http/Controllers/BatchFailedJobClearController.php:13
* @route '/horizon/batches/{batch}/failed'
*/
destroy.url = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{batch}', parsedArgs.batch.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchFailedJobClearController::destroy
* @see src/Http/Controllers/BatchFailedJobClearController.php:13
* @route '/horizon/batches/{batch}/failed'
*/
destroy.delete = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const clear = {
    destroy: Object.assign(destroy, destroy),
}

export default clear