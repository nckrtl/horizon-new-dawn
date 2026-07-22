import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringRecentJobController::destroy
* @see src/Http/Controllers/MonitoringRecentJobController.php:14
* @route '/horizon/monitoring/actions/clear-jobs/{tag}'
*/
export const destroy = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/monitoring/actions/clear-jobs/{tag}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringRecentJobController::destroy
* @see src/Http/Controllers/MonitoringRecentJobController.php:14
* @route '/horizon/monitoring/actions/clear-jobs/{tag}'
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
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringRecentJobController::destroy
* @see src/Http/Controllers/MonitoringRecentJobController.php:14
* @route '/horizon/monitoring/actions/clear-jobs/{tag}'
*/
destroy.delete = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const jobs = {
    destroy: Object.assign(destroy, destroy),
}

export default jobs