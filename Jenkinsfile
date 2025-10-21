pipeline {
    agent any

    environment {
        ImageRegistry     = 'k2ortiz'
        EC2_IP            = '52.4.172.57'
        DockerComposeFile = 'docker-compose.yml'
        DotEnvFile        = '.env'

        // Telegram API
        TELEGRAM_API_ID   = credentials('TELEGRAM_API_ID')
        TELEGRAM_API_HASH = credentials('TELEGRAM_API_HASH')

        // AWS credentials
        AWS_ACCESS_KEY_ID     = credentials('AWS_ACCESS_KEY_ID')
        AWS_SECRET_ACCESS_KEY = credentials('AWS_SECRET_ACCESS_KEY')
        AWS_DEFAULT_REGION     = 'us-east-1'
        S3_BUCKET              = credentials('S3_BUCKET')
    }

    stages {

        stage('Build Image') {
            steps {
                script {
                    echo "🛠️ Building Docker Image (with cache)..."
                    sh """
                        set -e
                        docker pull ${ImageRegistry}/${JOB_NAME}:latest || true
                        docker build \
                            --cache-from=${ImageRegistry}/${JOB_NAME}:latest \
                            -t ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER} \
                            -t ${ImageRegistry}/${JOB_NAME}:latest \
                            .
                    """
                }
            }
        }

        stage('Push Image') {
            steps {
                script {
                    echo "📦 Pushing Image to DockerHub..."
                    withCredentials([usernamePassword(credentialsId: 'docker-login', passwordVariable: 'PASS', usernameVariable: 'USER')]) {
                        sh """
                            set -e
                            echo "$PASS" | docker login -u "$USER" --password-stdin
                            docker push ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER}
                            docker push ${ImageRegistry}/${JOB_NAME}:latest
                        """
                    }
                }
            }
        }

        stage('Deploy Compose') {
            steps {
                script {
                    echo "🚀 Deploying on EC2 via Docker Compose..."

                    // Write environment variables into .env file (now expands correctly)
                    sh """
                    echo "🧾 Writing Jenkins credentials into ${DotEnvFile}..."
                    cat > ${DotEnvFile} <<EOF
AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION}
S3_BUCKET=${S3_BUCKET}
TELEGRAM_API_ID=${TELEGRAM_API_ID}
TELEGRAM_API_HASH=${TELEGRAM_API_HASH}
TELEGRAM_CHANNEL_URL=https://t.me/devtestingchannel
EOF
                    """

                    // Deploy remotely on EC2
                    sshagent(['ec2']) {
                        sh """
                        echo "📤 Uploading files to EC2..."
                        scp -o StrictHostKeyChecking=no ${DotEnvFile} ${DockerComposeFile} ubuntu@${EC2_IP}:/home/ubuntu/mywebsite/

                        echo "🔁 Restarting Docker Compose on EC2..."
                        ssh -o StrictHostKeyChecking=no ubuntu@${EC2_IP} "
                            cd /home/ubuntu/mywebsite &&
                            docker compose pull &&
                            docker compose --env-file .env down -v &&
                            docker compose --env-file .env up -d --build
                        "
                        """
                    }

                    echo "🧹 Cleaning up local .env file..."
                    sh "rm -f ${DotEnvFile}"
                }
            }
        }
    }
}
