#!/bin/bash

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Check if WP_PATH is set
if [ -z "$WP_PATH" ]; then
    echo "Error: WP_PATH must be set in .env"
    exit 1
fi

git pull

# Create zip file
echo "Creating zip package..."
./pack.sh

if [ ! -f "integration-for-billingo-fluentcart.zip" ]; then
    echo "Error: Failed to create zip file"
    exit 1
fi

# Copy to local WP path
echo "Copying to local WordPress installation..."
sudo cp integration-for-billingo-fluentcart.zip "$WP_PATH/"
sudo chown www-data:www-data $WP_PATH/integration-for-billingo-fluentcart.zip

if [ $? -ne 0 ]; then
    echo "Error: Failed to copy zip file to $WP_PATH"
    exit 1
fi

# Install via WP CLI
echo "Installing plugin via WP CLI..."
cd "$WP_PATH" && sudo -u www-data wp plugin install ./integration-for-billingo-fluentcart.zip --force --activate

if [ $? -ne 0 ]; then
    echo "Error: Failed to install plugin"
    exit 1
fi

# Cleanup
echo "Cleaning up..."
rm "$WP_PATH/integration-for-billingo-fluentcart.zip"

echo "Local deployment completed successfully!"
