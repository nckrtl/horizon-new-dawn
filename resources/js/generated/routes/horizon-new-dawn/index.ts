import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import dashboard074181 from './dashboard'
import instances from './instances'
import supervisors from './supervisors'
import monitoring from './monitoring'
import metrics from './metrics'
import batches from './batches'
import queues from './queues'
import jobs from './jobs'
import failedJobs from './failed-jobs'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::dashboard
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/horizon',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::dashboard
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::dashboard
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::dashboard
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

const horizonNewDawn = {
    dashboard: Object.assign(dashboard, dashboard074181),
    instances: Object.assign(instances, instances),
    supervisors: Object.assign(supervisors, supervisors),
    monitoring: Object.assign(monitoring, monitoring),
    metrics: Object.assign(metrics, metrics),
    batches: Object.assign(batches, batches),
    queues: Object.assign(queues, queues),
    jobs: Object.assign(jobs, jobs),
    failedJobs: Object.assign(failedJobs, failedJobs),
}

export default horizonNewDawn