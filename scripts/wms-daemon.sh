#!/usr/bin/env bash

# WMS Daemon Manager (Local Development & Container Environments)
# Manages background Redis Queue Workers and Schedule Runners.

set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="${BASE_DIR}/storage/wms-daemon.pid"
LOG_DIR="${BASE_DIR}/storage/logs"

# Ensure Oracle and OCI8 runtime environment
export ORACLE_HOME="${ORACLE_HOME:-/home/ughway/.oracle-client}"
export LD_LIBRARY_PATH="${ORACLE_HOME}/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
export PHP_INI_SCAN_DIR=":${ORACLE_HOME}"

mkdir -p "${LOG_DIR}"

start() {
    if [[ -f "${PID_FILE}" ]]; then
        echo "Daemon PID file exists (${PID_FILE}). Checking status..."
        status && return 0 || rm -f "${PID_FILE}"
    fi

    echo "Starting WMS Redis Queue Worker..."
    nohup php "${BASE_DIR}/artisan" queue:work redis \
        --queue=high,default \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --timeout=90 \
        >> "${LOG_DIR}/wms-queue-worker.log" 2>&1 &
    WORKER_PID=$!

    echo "Starting WMS Schedule Runner..."
    nohup php "${BASE_DIR}/artisan" schedule:work \
        >> "${LOG_DIR}/wms-scheduler.log" 2>&1 &
    SCHEDULER_PID=$!

    echo "${WORKER_PID}:${SCHEDULER_PID}" > "${PID_FILE}"
    echo "WMS Daemons started successfully."
    echo "  Queue Worker PID: ${WORKER_PID} (log: ${LOG_DIR}/wms-queue-worker.log)"
    echo "  Scheduler PID:    ${SCHEDULER_PID} (log: ${LOG_DIR}/wms-scheduler.log)"
}

stop() {
    if [[ ! -f "${PID_FILE}" ]]; then
        echo "No PID file found. Daemons are not running."
        return 0
    fi

    IFS=':' read -r WORKER_PID SCHEDULER_PID < "${PID_FILE}"

    echo "Stopping WMS Daemons gracefully..."
    if [[ -n "${WORKER_PID}" ]] && kill -0 "${WORKER_PID}" 2>/dev/null; then
        echo "  Terminating Queue Worker (PID ${WORKER_PID})..."
        kill -TERM "${WORKER_PID}" 2>/dev/null || true
    fi

    if [[ -n "${SCHEDULER_PID}" ]] && kill -0 "${SCHEDULER_PID}" 2>/dev/null; then
        echo "  Terminating Scheduler (PID ${SCHEDULER_PID})...."
        kill -TERM "${SCHEDULER_PID}" 2>/dev/null || true
    fi

    rm -f "${PID_FILE}"
    echo "WMS Daemons stopped."
}

status() {
    if [[ ! -f "${PID_FILE}" ]]; then
        echo "Status: STOPPED (no PID file)"
        return 1
    fi

    IFS=':' read -r WORKER_PID SCHEDULER_PID < "${PID_FILE}"
    WORKER_ALIVE=false
    SCHEDULER_ALIVE=false

    if [[ -n "${WORKER_PID}" ]] && kill -0 "${WORKER_PID}" 2>/dev/null; then
        WORKER_ALIVE=true
    fi

    if [[ -n "${SCHEDULER_PID}" ]] && kill -0 "${SCHEDULER_PID}" 2>/dev/null; then
        SCHEDULER_ALIVE=true
    fi

    echo "=== WMS Daemon Status ==="
    echo "  Queue Worker (PID ${WORKER_PID:-N/A}):   $([ "${WORKER_ALIVE}" = true ] && echo 'RUNNING' || echo 'STOPPED')"
    echo "  Scheduler    (PID ${SCHEDULER_PID:-N/A}):   $([ "${SCHEDULER_ALIVE}" = true ] && echo 'RUNNING' || echo 'STOPPED')"

    if [ "${WORKER_ALIVE}" = true ] && [ "${SCHEDULER_ALIVE}" = true ]; then
        return 0
    fi
    return 1
}

case "${1:-status}" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 1
        start
        ;;
    status)
        status
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
