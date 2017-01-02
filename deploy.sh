#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/geocoding-augmentation quay.io/keboola/geocoding-augmentation:$TRAVIS_TAG
docker images
docker push quay.io/keboola/geocoding-augmentation:$TRAVIS_TAG
