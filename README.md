# Introduction
This is a VOOT proxy service providing an OAuth 2.0 protected VOOT API
aggregating data from various external group providers.

# Configuration
To install the required dependencies run:

    $ sh docs/install_dependencies.sh

To set file permissions and setup the configuration file run:

    $ sh docs/configure.sh

Now you can modify the `tokenEndpoint` option in `config/proxy.ini` to point to
your `php-oauth` installation's token endpoint.

Don't forget to add the Apache configuration snippet that was shown as output
of the `configure.sh` script to your Apache configuration.
