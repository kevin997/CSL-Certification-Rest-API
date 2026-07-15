#!/bin/sh
# =============================================================================
# KURSA AI — GPU Ollama box provisioner (RTX A4000-class, Ubuntu 24 LTS)
#
# The GPU VPS is DISPOSABLE: every agent declares ollama_cpu (the media box)
# as failover, so this box can be powered off or destroyed at any time to
# save cost — the AI stack degrades to CPU speed instead of breaking.
# To bring capacity back: create a fresh GPU VPS at the provider, then run
# this script on it as root (or with sudo):
#
#   ssh administrator@<NEW_IP> 'sudo sh -s' < ops/gpu-ollama-provision.sh
#
# Afterwards point the stack at it:
#   1. /opt/csl-certification-rest-api/.env on 31.97.179.151:
#        OLLAMA_URL=http://<NEW_IP>:11434
#      then: cd /opt/csl-certification-rest-api && docker compose up -d
#   2. openclaw status script /root/kursa-marketing/gen-teaser.py: first URL
#      in its fallback chain.
# =============================================================================
set -e

APP_SERVER_IP="31.97.179.151"   # KURSA VPS + openclaw — allowed to reach ollama
MEDIA_SERVER_IP="31.97.75.62"   # CPU ollama box (kept allowed for symmetry)

# NVIDIA driver: most GPU VPS images ship one. Install only if missing.
if ! command -v nvidia-smi >/dev/null 2>&1; then
    apt-get update && apt-get install -y ubuntu-drivers-common
    ubuntu-drivers install
    echo "Driver installed — REBOOT, then re-run this script."
    exit 0
fi
nvidia-smi --query-gpu=name,memory.total --format=csv,noheader

# Ollama + service config (listen on all interfaces; UFW restricts reach).
curl -fsSL https://ollama.com/install.sh | sh
mkdir -p /etc/systemd/system/ollama.service.d
cat > /etc/systemd/system/ollama.service.d/override.conf <<EOF
[Service]
Environment=OLLAMA_HOST=0.0.0.0:11434
Environment=OLLAMA_KEEP_ALIVE=30m
Environment=OLLAMA_MAX_LOADED_MODELS=3
EOF
systemctl daemon-reload && systemctl enable --now ollama && systemctl restart ollama

# Firewall: SSH open, ollama only for our servers.
ufw allow 22/tcp
ufw allow from "$APP_SERVER_IP" to any port 11434 proto tcp
ufw allow from "$MEDIA_SERVER_IP" to any port 11434 proto tcp
ufw --force enable

# Models used by the KURSA stack.
for m in qwen2.5:14b qwen2.5:7b llama3.2:1b nomic-embed-text; do ollama pull "$m"; done

echo "GPU ollama ready: $(hostname -I | awk '{print $1}'):11434"
