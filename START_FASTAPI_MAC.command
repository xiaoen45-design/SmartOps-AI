#!/bin/bash
set -e
cd "$(dirname "$0")/smartops_fastapi"
exec bash run_mac.command
