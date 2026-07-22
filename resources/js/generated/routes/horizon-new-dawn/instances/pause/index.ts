import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::store
* @see src/Http/Controllers/HorizonPauseController.php:18
* @route '/horizon/instances/{instance}/pause'
*/
export const store = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/instances/{instance}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::store
* @see src/Http/Controllers/HorizonPauseController.php:18
* @route '/horizon/instances/{instance}/pause'
*/
store.url = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

    if (Array.isArray(args)) {
        args = {
            instance: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        instance: args.instance,
    }

    return store.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::store
* @see src/Http/Controllers/HorizonPauseController.php:18
* @route '/horizon/instances/{instance}/pause'
*/
store.post = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::destroy
* @see src/Http/Controllers/HorizonPauseController.php:33
* @route '/horizon/instances/{instance}/pause'
*/
export const destroy = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/instances/{instance}/pause',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::destroy
* @see src/Http/Controllers/HorizonPauseController.php:33
* @route '/horizon/instances/{instance}/pause'
*/
destroy.url = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

    if (Array.isArray(args)) {
        args = {
            instance: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        instance: args.instance,
    }

    return destroy.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonPauseController::destroy
* @see src/Http/Controllers/HorizonPauseController.php:33
* @route '/horizon/instances/{instance}/pause'
*/
destroy.delete = (args: { instance: string | number } | [instance: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const pause = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default pause