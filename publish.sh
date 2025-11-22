#!/bin/bash

# publish.sh - Copy plugin files to a destination directory
# Usage: ./publish.sh <destination_directory>

# Check if destination directory is provided
if [ -z "$1" ]; then
    echo "Error: No destination directory provided"
    echo "Usage: $0 <destination_directory>"
    exit 1
fi

DEST_DIR="$1"

# Create destination directory if it doesn't exist
if [ ! -d "$DEST_DIR" ]; then
    echo "Creating destination directory: $DEST_DIR"
    mkdir -p "$DEST_DIR"
fi

# Files and folders to copy
FILES_TO_COPY=(
    "integration-for-billingo-fluentcart.php"
    "LICENSE"
    "readme.txt"
)

FOLDERS_TO_COPY=(
    "includes"
    "languages"
)

echo "Publishing to: $DEST_DIR"
echo "----------------------------------------"

# Copy files
for file in "${FILES_TO_COPY[@]}"; do
    if [ -f "$file" ]; then
        echo "Copying file: $file"
        cp "$file" "$DEST_DIR/trunk"
    else
        echo "Warning: File not found: $file"
    fi
done

# Copy folders
for folder in "${FOLDERS_TO_COPY[@]}"; do
    if [ -d "$folder" ]; then
        echo "Copying folder: $folder"
        cp -r "$folder" "$DEST_DIR/trunk"
    else
        echo "Warning: Folder not found: $folder"
    fi
done

# Copy assets folder to $DEST_DIR/assets
if [ -d "assets" ]; then
    echo "Copying assets to: $DEST_DIR/assets"
    cp -r "assets" "$DEST_DIR/"
else
    echo "Warning: Assets folder not found"
fi

echo "----------------------------------------"
echo "Publishing complete!"
echo "Files copied to: $DEST_DIR"
