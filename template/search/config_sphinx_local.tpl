# ----------------------------------------------------
# Config generated by Search::CreateConfigFile()
# @author Mikhail Starovoyt
# @version 4.5.2
# ----------------------------------------------------

# ----------------------------------------------------
# Top Section
# ----------------------------------------------------
{$sTopSection}
# ----------------------------------------------------

indexer
{ldelim}
        mem_limit = 512M
{rdelim}

searchd
{ldelim}
	port					= 9312
	log						= D:/Sphinx/log/searchd.log
	query_log				= D:/Sphinx/log/query.log
	read_timeout			= 5
	max_children			= 30
	pid_file				= D:/Sphinx/log/searchd.pid
	max_matches				= 1000
	seamless_rotate			= 1
	preopen_indexes			= 0
	unlink_old				= 1
{rdelim}