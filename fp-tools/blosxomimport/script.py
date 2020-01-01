import glob
from os.path import exists, isdir, dirname
import os
from datetime import datetime

def create_dir(dir):
    if not exists(dir):
        os.makedirs(dir)

folds = glob.glob('*')
fns = []
for fold in folds:
    if not isdir(fold):
        continue
    elif 'fp-content' in fold:
        continue
    else:
        fns.extend(glob.glob(fold + '/*'))

base_file = open('template').read()

for fn in fns:
    content = open(fn).read()

    file_parts = base_file.split('|')
    body = ''.join(content.split('\n')[1:])
    heading = content.split('\n')[0]
    categories = dirname(fn)
    date = os.path.getmtime(fn)
    unix_date = int(date)
    hr_date = datetime.fromtimestamp(date)

    file_parts[3] = heading
    file_parts[5] = body
    file_parts[9] = str(unix_date)
    file_parts[11] = categories

    filled_template = '|'.join(file_parts)

    dst_fp = 'fp-content/content/{year}/{month}/entry{year}{month}{day}-{hour}{minute}{second}.txt'.format(
        year=str(hr_date.year)[2:],
        month='%.02d' % hr_date.month, day='%.02d' % hr_date.day, hour='%.02d' % hr_date.hour,
        minute='%.02d' % hr_date.minute, second='%.02d' % hr_date.second)

    create_dir('/'.join(dst_fp.split('/')[:-1]))

    with open(dst_fp, 'w') as dst:
        dst.write(filled_template)

