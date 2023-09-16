<?php

namespace phpbb\storage;

enum update_type: int
{
	case STORAGE_UPDATE_TYPE_CONFIG = 0;
	case STORAGE_UPDATE_TYPE_COPY = 1;
	case STORAGE_UPDATE_TYPE_MOVE = 2;
}
