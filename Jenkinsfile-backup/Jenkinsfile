pipeline {
    agent any

    environment {
        ImageRegistry = 'oluwaseuna'
        EC2_IP = '3.248.194.72'
    }


    stages{
        stage("buildImage") {
            steps{
                script {
                    echo "Build Docker Image"
                    sh "docker build -t ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER} ."
                }
            }
        }

        stage("pushImage") {
            steps{
                script {
                    echo "pushing Image to dockerhub"
                    withCredentials([usernamePassword(credentialsId: 'docker-login', passwordVariable: 'PASS', usernameVariable: 'USER')]) {
                        sh "echo $PASS | docker login -u $USER --password-stdin"
                        sh "docker push ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER}"
                    }
                }
            }
        }

        stage("deploy compose") {
            steps {
                script {
                    echo "deploying with docker compose"
                    def dockerCompose = 'docker compose -f docker-compose.yml --env-file dotenv up -d'
                    def dockerComposedown = "docker compose -f docker-compose.yml --env-file dotenv down"
                    sshagent(['ec2']) {
                        sh "scp -o StrictHostKeyChecking=no dotenv docker-compose.yml ubuntu@${EC2_IP}:/home/ubuntu"
                        sh "ssh -o StrictHostKeyChecking=no ubuntu@${EC2_IP} ${dockerComposedown}"
                        sh "ssh -o StrictHostKeyChecking=no ubuntu@${EC2_IP} ${dockerCompose}"
                    }
                }
            }
        }
    }
}