import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobClearAllController::destroy
* @see src/Http/Controllers/PendingJobClearAllController.php:13
* @route '/horizon/jobs/pending'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/jobs/pending',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobClearAllController::destroy
* @see src/Http/Controllers/PendingJobClearAllController.php:13
* @route '/horizon/jobs/pending'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobClearAllController::destroy
* @see src/Http/Controllers/PendingJobClearAllController.php:13
* @route '/horizon/jobs/pending'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const clear = {
    destroy: Object.assign(destroy, destroy),
}

export default clear