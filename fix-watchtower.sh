#!/bin/bash
# Script to fix Watchtower permissions and configuration
# For CSL-Certification-Rest-API

echo "=== Fixing Watchtower for CSL Certification API ==="
echo "This script will:"
echo "1. Stop the existing Watchtower container"
echo "2. Deploy a properly configured Watchtower with Docker socket permissions"
echo "3. Configure Watchtower to use the local registry"

# Stop the existing Watchtower container
echo -n "Stopping existing Watchtower container... "
sudo docker stop watchtower && sudo docker rm watchtower
if [ $? -eq 0 ]; then
    echo "SUCCESS"
else
    echo "WARNING: Could not stop existing container, may not exist yet."
fi

# Check if the docker group exists
if ! getent group docker > /dev/null; then
    echo "Creating docker group..."
    sudo groupadd docker
fi

# Make sure the current user is in the docker group
echo "Ensuring current user is in the docker group..."
sudo usermod -aG docker $USER
echo "You may need to log out and back in for group changes to take effect."

# Deploy the new Watchtower configuration
echo "Deploying new Watchtower configuration..."
sudo docker-compose -f docker-compose.watchtower.yml up -d

# Check if deployment was successful
if [ $? -eq 0 ]; then
    echo "SUCCESS: Watchtower redeployed with proper permissions."
    echo "Waiting 5 seconds for container to initialize..."
    sleep 5
    
    # Show logs to verify it's working
    echo "Showing recent Watchtower logs:"
    sudo docker logs watchtower --tail 10
else
    echo "ERROR: Failed to deploy Watchtower."
fi

# Add tag to local registry for CSL certificates image
echo "Ensuring image is properly tagged for local registry..."
IMAGE_ID=$(sudo docker images csl-certificates:latest -q)
if [ ! -z "$IMAGE_ID" ]; then
    sudo docker tag $IMAGE_ID localhost:5000/csl-certificates:latest
    sudo docker push localhost:5000/csl-certificates:latest
    echo "SUCCESS: Image tagged and pushed to local registry."
else
    echo "WARNING: Could not find csl-certificates:latest image."
fi

echo ""
echo "Setup complete! If you still see permission errors:"
echo "1. Try running: sudo chmod 666 /var/run/docker.sock"
echo "2. Or restart Docker with: sudo systemctl restart docker"
echo "3. You may need to log out and back in for group changes to take effect"
