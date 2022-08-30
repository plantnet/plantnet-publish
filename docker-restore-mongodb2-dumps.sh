#!/bin/bash

# Mathias, 2022-08-30
# Restore mongodb v2 dumps into newer version of mongodb, using bsondump and mongoimport (docker version)
# https://stackoverflow.com/a/51052217/5986614

if [ "$#" -lt 1 ]; then
	echo "Usage: $0 db_folder_to_restore"
	exit 1
fi

DBPATH=$1
DB=`basename "$DBPATH"`

echo "Restoring database $DB"

BSONFILES=`ls "$DBPATH" | grep ".bson"`

for BSON in $BSONFILES; do
	COLLECTION=`basename -s .bson $BSON`
	JSON="$COLLECTION.json"
	CMD="bsondump $DBPATH/$BSON > $DBPATH/$JSON"
	eval $CMD
	CMD2="sudo docker exec -i plantnet-publish_mongo_1 sh -c 'mongoimport --authenticationDatabase admin -d $DB -c $COLLECTION --drop' < $DBPATH/$JSON"
	eval $CMD2
done
