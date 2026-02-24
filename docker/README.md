# phpBB Docker Development Environment

This directory contains the Docker configuration for running phpBB in a local development environment.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

## getting Started

1. Navigate to the `docker` directory:
   ```bash
   cd docker
   ```

2. Start the containers:
   ```bash
   docker compose up -d
   ```

3. Access the phpBB installation in your browser at:
   [http://localhost:8080](http://localhost:8080)

## Database Configuration

The environment includes a MariaDB database pre-configured with the following credentials:

- **Host**: `db` (internal) / `localhost` (external port 33060)
- **Port**: `3306` (internal) / `33060` (external)
- **Database Name**: `phpbb`
- **User**: `phpbb`
- **Password**: `phpbb`
- **Root Password**: `root_password`

When installing phpBB, use these credentials.

## Installation

You can install phpBB and set up the development environment automatically using the provided `install.sh` script.

1.  Start the containers:
    ```bash
    docker compose up -d
    ```

2.  Make the script executable (if it isn't already):
    ```bash
    chmod +x install.sh
    ```

3.  Run the script:
    ```bash
    ./install.sh
    ```

This script will:
- Install Composer dependencies.
- Install phpBB using the configuration in `install-config.yml`.
- Enable DEBUG mode and set the environment to `development`.
- Increase PHP memory limit to 1024M.
- Restart Apache.

After the script finishes, your board will be ready at [http://localhost:8080](http://localhost:8080).

## Permission Issues (Linux)

If you encounter permission issues (e.g. phpBB cannot write to `../phpBB/config.php` or `../phpBB/cache/`), you may need to adjust permissions on your host machine:

```bash
chmod -R 777 ../phpBB/config.php ../phpBB/cache ../phpBB/store ../phpBB/files ../phpBB/images/avatars/upload
```

Or ensure your user ID matches the container's `www-data` user (typically 33).

## Useful Commands

- **Stop containers**:
  ```bash
  docker compose down
  ```

- **View logs**:
  ```bash
  docker compose logs -f
  ```

- **Access the phpBB container shell**:
  ```bash
  docker compose exec phpbb bash
  ```

## Notes

- The project root directory is mounted to `/var/www/html` in the container, so changes you make to the source code will be reflected immediately.
- Apache rewrite module is enabled.
