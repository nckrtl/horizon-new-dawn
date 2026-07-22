import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearController::destroy
* @see src/Http/Controllers/BatchClearController.php:15
* @route '/horizon/batches/{scope}'
*/
export const destroy = (args: { scope: string | number } | [scope: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/batches/{scope}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearController::destroy
* @see src/Http/Controllers/BatchClearController.php:15
* @route '/horizon/batches/{scope}'
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
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearController::destroy
* @see src/Http/Controllers/BatchClearController.php:15
* @route '/horizon/batches/{scope}'
*/
destroy.delete = (args: { scope: string | number } | [scope: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const clear = {
    destroy: Object.assign(destroy, destroy),
}

export default clear