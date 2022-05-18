#!/bin/sh

ZIP_FOLDER="shopgate-import-${TAG_NAME}"
ZIP_FILE="${ZIP_FOLDER}.zip"

# Remove mentioning the src/ structure when packaging
sed -i 's/src\///g' composer.json
sed -i 's/cart-integration-magento2-//g' composer.json

mkdir release/${ZIP_FOLDER}
rsync -a ./src/ release/${ZIP_FOLDER}
rsync -av --exclude-from './release/exclude-list.txt' ./ release/${ZIP_FOLDER}
cd ./release/${ZIP_FOLDER}
zip -r ../${ZIP_FILE} ./
cd ..
php validate_m2_package.php ${ZIP_FILE}
