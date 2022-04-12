#!/bin/bash

# Mathias, 2022-04-12
# Restore mongodb v2 dumps into newer version of mongodb, using bsondump and mongoimport
# https://stackoverflow.com/a/51052217/5986614

if [ "$#" -lt 1 ]; then
	echo "Usage: $0 db_folder_to_restore"
	exit 1
fi

DB=`basename "$1"`

echo "Restoring database $DB"

BSONFILES=`ls "$DB" | grep ".bson"`

for BSON in $BSONFILES; do
	COLLECTION=`basename -s .bson $BSON`
	JSON="$COLLECTION.json"
	CMD="bsondump $DB/$BSON > $DB/$JSON"
	eval $CMD
	CMD2="mongoimport -d $DB -c $COLLECTION $DB/$JSON"
	eval $CMD2
done

