# JSON Storage

Collections are stored separately in `storage/data`. Writes use lock files plus temporary files and atomic rename to reduce corruption risk. Runtime secrets must live outside Git-tracked collection files.
