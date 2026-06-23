#!/bin/bash
set -e

# Install mobile app dependencies
cd webapps/classexpress-mobile
npm install --legacy-peer-deps --silent
cd ../..
