#!/bin/sh
set -e

REPO="antimatter-studios/docker-dev-tools"
BINARY="ddt"
INSTALL_DIR="${HOME}/.local/bin"

# Detect OS
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
case "$OS" in
    darwin) OS="darwin" ;;
    linux) OS="linux" ;;
    *) echo "Unsupported OS: $OS"; exit 1 ;;
esac

# Detect architecture
ARCH=$(uname -m)
case "$ARCH" in
    x86_64|amd64) ARCH="amd64" ;;
    aarch64|arm64) ARCH="arm64" ;;
    *) echo "Unsupported architecture: $ARCH"; exit 1 ;;
esac

# Get latest version from GitHub API
echo "Fetching latest release..."
VERSION=$(curl -fsSL "https://api.github.com/repos/${REPO}/releases/latest" | grep '"tag_name"' | sed -E 's/.*"tag_name": *"([^"]+)".*/\1/')

if [ -z "$VERSION" ]; then
    echo "Error: could not determine latest version"
    exit 1
fi

# Strip leading 'v' for the archive name
VERSION_NUM="${VERSION#v}"

ARCHIVE="${BINARY}_${VERSION_NUM}_${OS}_${ARCH}.tar.gz"
URL="https://github.com/${REPO}/releases/download/${VERSION}/${ARCHIVE}"

echo "Downloading ${BINARY} ${VERSION} for ${OS}/${ARCH}..."

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

curl -fsSL "$URL" -o "${TMPDIR}/${ARCHIVE}"
tar -xzf "${TMPDIR}/${ARCHIVE}" -C "$TMPDIR"

# Ensure install directory exists
mkdir -p "$INSTALL_DIR"

# Install binary
mv "${TMPDIR}/${BINARY}" "${INSTALL_DIR}/${BINARY}"
chmod +x "${INSTALL_DIR}/${BINARY}"

echo "${BINARY} ${VERSION} installed to ${INSTALL_DIR}/${BINARY}"

# Check if install dir is on PATH
case ":$PATH:" in
    *":${INSTALL_DIR}:"*) ;;
    *) echo "WARNING: ${INSTALL_DIR} is not in your PATH. Add it with:"
       echo "  export PATH=\"${INSTALL_DIR}:\$PATH\"" ;;
esac
