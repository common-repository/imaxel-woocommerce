# imaxel-woocommerce

WordPress plugin to integrate with Imaxel editors

## Developer guide

See the `doc/developer-guide.md` file for a more complete development documentation.

## Project Layout
	|-/				root folder
    |-assets/       assets folder (css, js, img)
    |-includes/     library folder
    |-language/     language files

## Requisites

Woocommerce plugin installed

## Development


## Translation

First, you need to set up the `imaxel-woocommerce-locale` repository as a `git submodule`:

    git submodule add --name language git@bitbucket.org:imaxel-lab/imaxel-woocommerce-locale.git
    git submodule init
    git submodule update --remote --merge
    git mv imaxel-woocommerce-locale/ language

    // If you need to remove the git clone by mistake, use this
    git config --remove-section submodule."imaxel-woocommerce-locale"


The `grunt tasks` works against the language folder so it is important that we have configured the previous repository in a folder called `"language"`

Afterwards, run the `grunt pot` task to extract all literals from your project and create a POT file named `imaxel.pot` in the `language` folder

Finally, you have to follow this instructions:
1. Push in translation repository
2. Pull Weblate in `wp-imaxel-woocommerce` project
3. Make the changes, commit and push in Weblate
4. Pull in translation repository
5. Push the submodule commit in `imaxel-woocommerce` repository to finish

## Deployment

The deployment workflow consists in uploading the source code to the Imaxel Wordpress SVN repository

Repository url: https://plugins.svn.wordpress.org/imaxel-woocommerce/

Repository credentials stored in Keeweb https://drive.google.com/file/d/1FMJu-V3mkajNwAyd4MaXWDbJKUPJaat5/view?usp=sharing

Steps:

    Deployment structure

    |-assets/
    |-includes/
    |-language/
    |-install.sql
    |-README.md
    |-README.txt
    |-woocommerce-imaxel.php

    * Create version tag folder in svn/tags/

    * Copy files to svn/tags/version

    * Copy files to svn/trunk

    * Commit files to SVN repository

### Continuous Integration and S3 deployment

* Gruntfile.js publish task zips and uploads output to S3 bucket `applications-imaxel`. Using branch and version number variables for the output file name.

* [Changelog](https://applications-imaxel.s3.eu-west-1.amazonaws.com/imaxel-woocommerce/changelog.md)

* [Buildserver](https://buildserver.imaxel.com/)

* [Dev last version](https://applications-imaxel.s3.eu-west-1.amazonaws.com/imaxel-woocommerce/imaxel-woocommerce_last_dev.zip)

* [Master last version](https://applications-imaxel.s3.eu-west-1.amazonaws.com/imaxel-woocommerce/imaxel-woocommerce_last_master.zip)

## Release History

See `changelog.md`

## License

See `LICENSE`